<?php

namespace App\Http\Controllers;

use App\Mail\ClientMessageReceived;
use App\Models\ProjectDocument;
use App\Models\QuoteRequest;
use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectCollaborationController extends Controller
{
    private function clientQuote(Request $request): QuoteRequest
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D']]);
        return \App\Support\ClientAccess::quote($data['token']);
    }

    private function records(QuoteRequest $quote): array
    {
        $locked = ! $quote->handed_over_at;
        return [
            'messages' => $quote->messages()->orderByDesc('id')->limit(150)->get(['id', 'author', 'body', 'created_at'])->reverse()->values(),
            // Az átadandó kód / csomag a kódátadásig zárolt: az ügyfél látja, hogy készen áll, de nem töltheti le.
            'documents' => $quote->documents()->orderByDesc('id')
                ->get(['id', 'title', 'filename', 'mime_type', 'size_bytes', 'is_deliverable', 'created_at'])
                ->map(fn (ProjectDocument $d) => $d->setAttribute('locked', $d->is_deliverable && $locked)),
        ];
    }

    public function clientIndex(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->records($this->clientQuote($request))])
            ->header('Cache-Control', 'no-store, private');
    }

    public function clientMessage(Request $request): JsonResponse
    {
        $quote = $this->clientQuote($request);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:3000']]);
        if ($quote->messages()->count() >= 500) {
            throw ValidationException::withMessages(['body' => 'Elérted a projekt üzenetkorlátját.']);
        }
        $message = $quote->messages()->create(['author' => 'client', 'body' => trim($data['body'])]);
        $this->notifyAdmin($quote, $message);
        return response()->json(['data' => $message->only(['id', 'author', 'body', 'created_at'])], 201)->header('Cache-Control', 'no-store, private');
    }

    /** E-mail az adminnak új ügyfélüzenetről – projektenként legfeljebb 10 percenként egy, a válasz után küldve. */
    private function notifyAdmin(QuoteRequest $quote, \App\Models\ProjectMessage $message): void
    {
        $owner = config('portfolio.admin_email');
        if (! $owner || ! \App\Support\Mailer::canSend()) {
            return;
        }
        if (! Cache::add('client-message-mail:'.$quote->id, true, now()->addMinutes(10))) {
            return;
        }
        $url = config('portfolio.frontend_url').'/admin?ajanlat='.$quote->id;
        \Illuminate\Support\defer(function () use ($owner, $quote, $message, $url) {
            try {
                Mail::to($owner)->send(new ClientMessageReceived($quote, $message, $url));
            } catch (\Throwable $exception) {
                Log::error('Client message notice failed', ['quote_id' => $quote->id, 'exception_type' => $exception::class]);
            }
        });
    }

    public function clientDownload(Request $request, ProjectDocument $document): StreamedResponse
    {
        $quote = $this->clientQuote($request);
        abort_unless($document->quote_request_id === $quote->id, 404);
        // Kódátadás előtt az átadási csomag nem tölthető le (a fennmaradó összeg kifizetése a feltétel).
        abort_if($document->is_deliverable && ! $quote->handed_over_at, 403, 'Az átadási csomag a kódátadás után tölthető le.');
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);
        return Storage::disk('local')->download($document->storage_path, $document->filename, [
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function adminIndex(QuoteRequest $quoteRequest): JsonResponse
    {
        $records = $this->records($quoteRequest);
        // Az admin megnyitotta a beszélgetést: az ügyfél üzenetei olvasottá válnak.
        $quoteRequest->messages()->unreadFromClient()->update(['read_at' => now()]);
        return response()->json(['data' => $records]);
    }

    public function adminMessage(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:3000']]);
        if ($quoteRequest->messages()->count() >= 500) {
            throw ValidationException::withMessages(['body' => 'Elérted a projekt üzenetkorlátját.']);
        }
        $message = $quoteRequest->messages()->create(['author' => 'admin', 'body' => trim($data['body'])]);
        $quoteRequest->markFirstResponse();
        AdminAudit::record($request, 'project_message_sent', 'quote_request', $quoteRequest->id);
        return response()->json(['data' => $message], 201);
    }

    public function adminUpload(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $deliverable = $request->boolean('deliverable');
        // Átadandó kód / csomag: ZIP (vagy PDF dokumentáció) nagyobb méretkorláttal; egyéb dokumentum: PDF vagy kép, max. 10 MB.
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'deliverable' => ['sometimes', 'boolean'],
            'file' => $deliverable
                ? ['required', 'file', 'mimes:zip,pdf', 'max:'.(config('portfolio.deliverable_max_mb') * 1024)]
                : ['required', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
        ]);
        if ($quoteRequest->documents()->count() >= 30) {
            throw ValidationException::withMessages(['file' => 'Legfeljebb 30 dokumentum tölthető fel projektenként.']);
        }
        $file = $data['file'];
        $path = $file->store('client-projects/'.$quoteRequest->id.($deliverable ? '/deliverables' : ''), 'local');
        try {
            $document = $quoteRequest->documents()->create([
                'title' => trim($data['title']), 'filename' => basename($file->getClientOriginalName()),
                'storage_path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(), 'is_deliverable' => $deliverable,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        AdminAudit::record($request, 'project_document_uploaded', 'quote_request', $quoteRequest->id);
        return response()->json(['data' => $document], 201);
    }

    public function adminDownload(QuoteRequest $quoteRequest, ProjectDocument $document): StreamedResponse
    {
        abort_unless($document->quote_request_id === $quoteRequest->id, 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);
        return Storage::disk('local')->download($document->storage_path, $document->filename, [
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function adminDelete(Request $request, QuoteRequest $quoteRequest, ProjectDocument $document): JsonResponse
    {
        abort_unless($document->quote_request_id === $quoteRequest->id, 404);
        $path = $document->storage_path;
        $document->delete();
        Storage::disk('local')->delete($path);
        AdminAudit::record($request, 'project_document_deleted', 'quote_request', $quoteRequest->id);
        return response()->json(['message' => 'Dokumentum törölve.']);
    }
}
