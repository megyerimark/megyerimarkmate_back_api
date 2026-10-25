<?php

namespace App\Http\Controllers;

use App\Models\ProjectDocument;
use App\Models\QuoteRequest;
use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectCollaborationController extends Controller
{
    private function clientQuote(Request $request): QuoteRequest
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D']]);
        return QuoteRequest::where('portal_token_hash', hash('sha256', $data['token']))
            ->where('portal_token_expires_at', '>', now())
            ->firstOrFail();
    }

    private function records(QuoteRequest $quote): array
    {
        return [
            'messages' => $quote->messages()->orderByDesc('id')->limit(150)->get(['id', 'author', 'body', 'created_at'])->reverse()->values(),
            'documents' => $quote->documents()->orderByDesc('id')->get(['id', 'title', 'filename', 'mime_type', 'size_bytes', 'created_at']),
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
        return response()->json(['data' => $message], 201)->header('Cache-Control', 'no-store, private');
    }

    public function clientDownload(Request $request, ProjectDocument $document): StreamedResponse
    {
        $quote = $this->clientQuote($request);
        abort_unless($document->quote_request_id === $quote->id, 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);
        return Storage::disk('local')->download($document->storage_path, $document->filename, [
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function adminIndex(QuoteRequest $quoteRequest): JsonResponse
    {
        return response()->json(['data' => $this->records($quoteRequest)]);
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
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
        ]);
        if ($quoteRequest->documents()->count() >= 30) {
            throw ValidationException::withMessages(['file' => 'Legfeljebb 30 dokumentum tölthető fel projektenként.']);
        }
        $file = $data['file'];
        $path = $file->store('client-projects/'.$quoteRequest->id, 'local');
        try {
            $document = $quoteRequest->documents()->create([
                'title' => trim($data['title']), 'filename' => basename($file->getClientOriginalName()),
                'storage_path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
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
