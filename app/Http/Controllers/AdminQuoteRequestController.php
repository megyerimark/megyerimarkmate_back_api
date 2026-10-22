<?php

namespace App\Http\Controllers;

use App\Mail\ProposalReady;
use App\Mail\ProjectAccessRenewed;
use App\Models\QuoteRequest;
use App\Support\AdminAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminQuoteRequestController extends Controller
{
    private const STATUSES = ['new', 'reviewing', 'quoted', 'accepted', 'rejected', 'completed'];

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'q' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $query = QuoteRequest::query();
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $term = addcslashes(trim($filters['q']), '%_\\');
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('customer_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('company_name', 'like', "%{$term}%");
            });
        }
        return response()->json($query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15)));
    }

    public function show(QuoteRequest $quoteRequest): JsonResponse
    {
        return response()->json(['data' => $quoteRequest]);
    }

    public function update(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'required', Rule::in(self::STATUSES)],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'quoted_price_huf' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999999999'],
            'proposal_summary' => ['sometimes', 'nullable', 'string', 'max:7000'],
            'proposal_schedule' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'proposal_terms' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'proposal_valid_until' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);
        if ($data === []) {
            return response()->json(['message' => 'Nincs módosítandó adat.'], 422);
        }
        // The "quoted" status is never a cosmetic switch: only a successful mail send sets it.
        if (($data['status'] ?? null) === 'quoted' && ! $quoteRequest->quoted_at) {
            throw ValidationException::withMessages(['status' => 'Először mentsd az ajánlatot, majd használd az „Ajánlat tényleges elküldése” gombot.']);
        }
        $proposalFields = ['quoted_price_huf', 'proposal_summary', 'proposal_schedule', 'proposal_terms', 'proposal_valid_until'];
        if ($quoteRequest->quoted_at && collect($proposalFields)->contains(fn ($key) => array_key_exists($key, $data) &&
            ($key === 'proposal_valid_until' ? $quoteRequest->{$key}?->format('Y-m-d') : $quoteRequest->{$key}) != $data[$key])) {
            throw ValidationException::withMessages(['proposal' => 'Az elküldött ajánlat tartalma nem módosítható csendben.']);
        }
        if ($quoteRequest->proposal_accepted_at && isset($data['status']) && $data['status'] !== $quoteRequest->status && ! ($quoteRequest->status === 'accepted' && $data['status'] === 'completed')) {
            throw ValidationException::withMessages(['status' => 'Az elfogadott ajánlat állapotát a megerősítés megőrzése érdekében nem lehet felülírni.']);
        }
        foreach (['status', 'admin_notes', ...$proposalFields] as $key) {
            if (array_key_exists($key, $data)) {
                $quoteRequest->{$key} = $data[$key];
            }
        }
        $quoteRequest->save();
        AdminAudit::record($request, 'updated', 'quote_request', $quoteRequest->id);
        return response()->json(['message' => 'Az ajánlatkérés frissítve.', 'data' => $quoteRequest->fresh()]);
    }

    public function sendProposal(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        if (! config('portfolio.mail_enabled') || in_array(config('mail.default'), ['log', 'array', 'null'], true)) {
            return response()->json(['message' => 'Valós e-mail-küldés nincs beállítva. Ellenőrizd a MAIL_MAILER és PORTFOLIO_MAIL_ENABLED beállításokat.'], 503);
        }
        if ($quoteRequest->quoted_at || $quoteRequest->proposal_accepted_at) {
            return response()->json(['message' => 'Az ajánlat már elküldésre került; automatikus ismételt küldés nem engedélyezett.'], 422);
        }
        if (! $quoteRequest->quoted_price_huf || ! $quoteRequest->proposal_summary || ! $quoteRequest->proposal_valid_until) {
            throw ValidationException::withMessages(['proposal' => 'A küldéshez összeg, részletes ajánlati tartalom és érvényességi dátum szükséges.']);
        }
        if ($quoteRequest->proposal_valid_until->isBefore(today())) {
            throw ValidationException::withMessages(['proposal_valid_until' => 'Az ajánlat érvényessége lejárt.']);
        }
        // Existing client links stay valid. Older enquiries get a token only when necessary.
        if ($quoteRequest->portal_token_encrypted) {
            $token = Crypt::decryptString($quoteRequest->portal_token_encrypted);
        } else {
            $token = bin2hex(random_bytes(32));
            $quoteRequest->portal_token_hash = hash('sha256', $token);
            $quoteRequest->portal_token_encrypted = Crypt::encryptString($token);
            $quoteRequest->save();
        }
        $url = config('portfolio.frontend_url').'/ugyfel/projekt#'.$token;
        try {
            Mail::to($quoteRequest->email)->send(new ProposalReady($quoteRequest, $url));
        } catch (\Throwable $exception) {
            Log::error('Proposal delivery failed', ['quote_id' => $quoteRequest->id, 'exception_type' => $exception::class]);
            return response()->json(['message' => 'Az e-mail-küldés sikertelen volt. Az ajánlat állapota változatlan. Ellenőrizd az SMTP-beállításokat.'], 503);
        }
        $quoteRequest->status = 'quoted';
        $quoteRequest->quoted_at = now();
        $quoteRequest->save();
        AdminAudit::record($request, 'proposal_sent', 'quote_request', $quoteRequest->id);
        return response()->json(['message' => 'Az ajánlat e-mailben elküldve.', 'data' => $quoteRequest->fresh()]);
    }

    public function rotateClientAccess(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        if (! config('portfolio.mail_enabled') || in_array(config('mail.default'), ['log', 'array', 'null'], true)) {
            return response()->json(['message' => 'Valós SMTP-küldés szükséges az új link biztonságos kézbesítéséhez.'], 503);
        }
        $token = bin2hex(random_bytes(32));
        $url = config('portfolio.frontend_url').'/ugyfel/projekt#'.$token;
        try {
            Mail::to($quoteRequest->email)->send(new ProjectAccessRenewed($quoteRequest, $url));
        } catch (\Throwable $exception) {
            Log::error('Project link renewal failed', ['quote_id' => $quoteRequest->id, 'exception_type' => $exception::class]);
            return response()->json(['message' => 'Nem sikerült kézbesíteni az új linket. A régi hozzáférés változatlan.'], 503);
        }
        $quoteRequest->portal_token_hash = hash('sha256', $token);
        $quoteRequest->portal_token_encrypted = Crypt::encryptString($token);
        $quoteRequest->save();
        AdminAudit::record($request, 'portal_access_rotated', 'quote_request', $quoteRequest->id);
        return response()->json(['message' => 'Új privát link elküldve. A korábbi link érvénytelenítve.']);
    }

    public function destroy(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $id = $quoteRequest->id;
        $paths = $quoteRequest->documents()->pluck('storage_path')->all();
        $quoteRequest->delete();
        \Illuminate\Support\Facades\Storage::disk('local')->delete($paths);
        AdminAudit::record($request, 'deleted', 'quote_request', $id);
        return response()->json(['message' => 'Az ajánlatkérés véglegesen törölve.']);
    }
}
