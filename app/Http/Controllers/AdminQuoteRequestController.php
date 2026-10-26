<?php

namespace App\Http\Controllers;

use App\Mail\ProposalReady;
use App\Mail\ProjectAccessRenewed;
use App\Models\QuoteRequest;
use App\Models\StudioClientNote;
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
        $query = QuoteRequest::query()->withCount(['messages as unread_messages_count' => fn (Builder $q) => $q->where('author', 'client')->whereNull('read_at')]);
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
            'deposit_percent' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'proposal_summary' => ['sometimes', 'nullable', 'string', 'max:7000'],
            'proposal_schedule' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'proposal_terms' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'proposal_valid_until' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'follow_up_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        if ($data === []) {
            return response()->json(['message' => 'Nincs módosítandó adat.'], 422);
        }
        // No admin API call may impersonate a client's proposal acceptance or a successful mail send.
        if (isset($data['status']) && $data['status'] !== $quoteRequest->status) {
            $allowed = match ($quoteRequest->status) {
                'new' => ['reviewing', 'rejected'],
                'reviewing' => ['new', 'rejected'],
                'rejected' => ['reviewing'],
                'quoted' => ['rejected'],
                'accepted' => ['completed'],
                default => [],
            };
            if (! in_array($data['status'], $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'Ez az állapotváltás itt nem engedélyezett. Ajánlatküldéshez és ügyfél-elfogadáshoz a megfelelő folyamatot használd.']);
            }
        }
        $proposalFields = ['quoted_price_huf', 'deposit_percent', 'proposal_summary', 'proposal_schedule', 'proposal_terms', 'proposal_valid_until'];
        if ($quoteRequest->quoted_at && collect($proposalFields)->contains(fn ($key) => array_key_exists($key, $data) &&
            ($key === 'proposal_valid_until' ? $quoteRequest->{$key}?->format('Y-m-d') : $quoteRequest->{$key}) != $data[$key])) {
            throw ValidationException::withMessages(['proposal' => 'Az elküldött ajánlat tartalma nem módosítható csendben.']);
        }
        if ($quoteRequest->proposal_accepted_at && isset($data['status']) && $data['status'] !== $quoteRequest->status && ! ($quoteRequest->status === 'accepted' && $data['status'] === 'completed')) {
            throw ValidationException::withMessages(['status' => 'Az elfogadott ajánlat állapotát a megerősítés megőrzése érdekében nem lehet felülírni.']);
        }
        foreach (['status', 'admin_notes', 'follow_up_at', ...$proposalFields] as $key) {
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
            // Renew older, still-valid links when sending the first proposal.
            if (! $quoteRequest->portal_token_expires_at || $quoteRequest->portal_token_expires_at->isPast()) {
                $token = bin2hex(random_bytes(32));
                $quoteRequest->portal_token_hash = hash('sha256', $token);
                $quoteRequest->portal_token_encrypted = Crypt::encryptString($token);
                $quoteRequest->portal_token_expires_at = now()->addDays(config('portfolio.client_access_days'));
                $quoteRequest->save();
            }
        } else {
            $token = bin2hex(random_bytes(32));
            $quoteRequest->portal_token_hash = hash('sha256', $token);
            $quoteRequest->portal_token_encrypted = Crypt::encryptString($token);
            $quoteRequest->portal_token_expires_at = now()->addDays(config('portfolio.client_access_days'));
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
        $quoteRequest->markFirstResponse();
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
        $quoteRequest->portal_token_expires_at = now()->addDays(config('portfolio.client_access_days'));
        $quoteRequest->save();
        AdminAudit::record($request, 'portal_access_rotated', 'quote_request', $quoteRequest->id);
        return response()->json(['message' => 'Új privát link elküldve. A korábbi link érvénytelenítve.']);
    }

    /** Előleg kézi jóváírása (pl. banki átutalás) – bevételként is rögzül a Pénzügyekben. */
    public function markDepositPaid(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $data = $request->validate(['paid_on' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:today']]);
        $amount = $quoteRequest->depositAmount();
        if (! $quoteRequest->proposal_accepted_at || ! $amount) {
            throw ValidationException::withMessages(['deposit' => 'Ehhez az ajánlathoz nincs elfogadott előleg.']);
        }
        if ($quoteRequest->deposit_paid_at) {
            throw ValidationException::withMessages(['deposit' => 'Az előleg már be van jelölve.']);
        }
        $date = $data['paid_on'] ?? now()->toDateString();
        $quoteRequest->forceFill(['deposit_paid_at' => $date.' '.now()->format('H:i:s'), 'deposit_paid_via' => 'transfer'])->save();
        \App\Models\FinanceEntry::create([
            'type' => 'income', 'occurred_on' => $date, 'amount_huf' => $amount,
            'vat_rate' => \App\Models\AdminSetting::finance()['vat_registered'] ? 27 : 0,
            'category' => 'Előleg', 'description' => 'Előleg (átutalás) – #'.$quoteRequest->id.' '.$quoteRequest->customer_name,
            'quote_request_id' => $quoteRequest->id, 'source' => 'manual',
        ]);
        AdminAudit::record($request, 'deposit_marked_paid', 'quote_request', $quoteRequest->id);
        return response()->json(['message' => 'Előleg jóváírva.', 'data' => $quoteRequest->fresh()]);
    }

    /** Kézi jelölés: az érdeklődővel felvettem a kapcsolatot (telefon, e-mail, üzenet). Csak egyszer állítja be az időpontot. */
    public function markContacted(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $quoteRequest->markFirstResponse();
        AdminAudit::record($request, 'marked_contacted', 'quote_request', $quoteRequest->id);
        return response()->json(['data' => $quoteRequest->fresh()]);
    }

    /** Excelben megnyitható CSV (pontosvessző, UTF-8 BOM). A cellák képlet-injektálás ellen védettek. */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters = $request->validate(['status' => ['sometimes', Rule::in(self::STATUSES)]]);
        AdminAudit::record($request, 'exported', 'quote_requests');
        $header = ['Azonosító', 'Beérkezett', 'Név', 'E-mail', 'Telefon', 'Cég', 'Weboldaltípus', 'Státusz', 'Költségkeret',
            'Ajánlat összege (Ft)', 'Forrás', 'Hivatkozó oldal', 'Kampány', 'Első válasz', 'Utánkövetés', 'Elfogadva'];
        $safe = static function (mixed $value): string {
            $text = (string) ($value ?? '');
            return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
        };

        return response()->streamDownload(function () use ($header, $safe, $filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';', '"', '');
            $query = QuoteRequest::query()->when(! empty($filters['status']), fn ($builder) => $builder->where('status', $filters['status']));
            $query->orderBy('id')->chunkById(200, function ($rows) use ($out, $safe) {
                foreach ($rows as $row) {
                    fputcsv($out, array_map($safe, [
                        $row->id, $row->created_at?->format('Y-m-d H:i'), $row->customer_name, $row->email, $row->phone,
                        $row->company_name, $row->website_type, $row->status, $row->budget_range, $row->quoted_price_huf,
                        $row->utm_source ?: $row->source, $row->referrer_host, $row->utm_campaign,
                        $row->first_response_at?->format('Y-m-d H:i'), $row->follow_up_at?->format('Y-m-d'),
                        $row->proposal_accepted_at?->format('Y-m-d'),
                    ]), ';', '"', '');
                }
            });
            fclose($out);
        }, 'ajanlatkeresek-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $id = $quoteRequest->id;
        $clientEmail = $quoteRequest->email;
        $paths = $quoteRequest->documents()->pluck('storage_path')->all();
        $quoteRequest->delete();
        // Do not retain standalone CRM notes when the last enquiry of this contact is deleted.
        if (! QuoteRequest::where('email', $clientEmail)->exists()) {
            StudioClientNote::where('client_email', $clientEmail)->delete();
        }
        \Illuminate\Support\Facades\Storage::disk('local')->delete($paths);
        AdminAudit::record($request, 'deleted', 'quote_request', $id);
        return response()->json(['message' => 'Az ajánlatkérés véglegesen törölve.']);
    }
}
