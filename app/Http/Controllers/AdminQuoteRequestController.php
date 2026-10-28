<?php

namespace App\Http\Controllers;

use App\Mail\ProjectCompleted;
use App\Mail\ProjectHandedOver;
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
                default => [],
            };
            if ($data['status'] === 'completed') {
                throw ValidationException::withMessages(['status' => 'A projekt lezárásához a „Projekt kész” gombot használd – az küldi el a végleges e-mailt az ügyfélnek.']);
            }
            if (! in_array($data['status'], $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'Ez az állapotváltás itt nem engedélyezett. Ajánlatküldéshez és ügyfél-elfogadáshoz a megfelelő folyamatot használd.']);
            }
        }
        $proposalFields = ['quoted_price_huf', 'deposit_percent', 'proposal_summary', 'proposal_schedule', 'proposal_terms', 'proposal_valid_until'];
        if ($quoteRequest->quoted_at && collect($proposalFields)->contains(fn ($key) => array_key_exists($key, $data) &&
            ($key === 'proposal_valid_until' ? $quoteRequest->{$key}?->format('Y-m-d') : $quoteRequest->{$key}) != $data[$key])) {
            throw ValidationException::withMessages(['proposal' => 'Az elküldött ajánlat tartalma nem módosítható csendben.']);
        }
        if ($quoteRequest->proposal_accepted_at && isset($data['status']) && $data['status'] !== $quoteRequest->status) {
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
        if (! \App\Support\Mailer::canSend()) {
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
        $minDeposit = (int) config('portfolio.min_deposit_percent');
        if ($minDeposit > 0 && (int) $quoteRequest->deposit_percent < $minDeposit) {
            throw ValidationException::withMessages(['deposit_percent' => "Az ajánlathoz legalább {$minDeposit}% előleget kell megadni (a munka a befizetése után indul)."]);
        }
        $url = $this->portalUrl($quoteRequest);
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

    /**
     * Az ügyfél privát projektlinkje. A meglévő, érvényes link marad; lejárt vagy hiányzó link helyett újat készít.
     * Kizárólag e-mail-küldés előtt hívd: a link csak az ügyfélnek küldött levélben jelenik meg.
     */
    private function portalUrl(QuoteRequest $quoteRequest): string
    {
        $token = $quoteRequest->portal_token_encrypted ? Crypt::decryptString($quoteRequest->portal_token_encrypted) : null;
        if (! $token || ! $quoteRequest->portal_token_expires_at || $quoteRequest->portal_token_expires_at->isPast()) {
            $token = bin2hex(random_bytes(32));
            $quoteRequest->portal_token_hash = hash('sha256', $token);
            $quoteRequest->portal_token_encrypted = Crypt::encryptString($token);
            $quoteRequest->portal_token_expires_at = now()->addDays(config('portfolio.client_access_days'));
            $quoteRequest->save();
        }
        return config('portfolio.frontend_url').'/ugyfel/projekt#'.$token;
    }

    /**
     * „Projekt kész”: végleges e-mail az ügyfélnek a fennmaradó összeggel és a fizetési lehetőséggel.
     * A státusz csak sikeres kézbesítés után vált „completed”-re; a fennmaradó összeget ekkor rögzítjük.
     */
    public function complete(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        if (! \App\Support\Mailer::canSend()) {
            return response()->json(['message' => 'Valós e-mail-küldés nincs beállítva, így a végleges e-mail nem küldhető ki. Ellenőrizd a MAIL_MAILER és PORTFOLIO_MAIL_ENABLED beállításokat.'], 503);
        }
        // A korábban (végleges e-mail nélkül) „kész”-re állított projektek is lezárhatók így.
        $legacyCompleted = $quoteRequest->status === 'completed' && ! $quoteRequest->completed_at;
        if (! $quoteRequest->proposal_accepted_at || ($quoteRequest->status !== 'accepted' && ! $legacyCompleted)) {
            throw ValidationException::withMessages(['status' => 'Csak elfogadott, folyamatban lévő projekt jelölhető késznek.']);
        }
        if ($quoteRequest->depositAmount() && ! $quoteRequest->deposit_paid_at) {
            throw ValidationException::withMessages(['deposit' => 'Az előleg még nem érkezett meg. Előbb rendezd (vagy jelöld beérkezettnek), utána zárható le a projekt.']);
        }
        $remaining = $quoteRequest->remainingAmount();
        $url = $this->portalUrl($quoteRequest);
        // A levél a rögzítendő összeget mutassa (még mentés nélkül).
        $quoteRequest->final_amount_huf = $remaining;
        try {
            Mail::to($quoteRequest->email)->send(new ProjectCompleted($quoteRequest, $url));
        } catch (\Throwable $exception) {
            Log::error('Project completion mail failed', ['quote_id' => $quoteRequest->id, 'exception_type' => $exception::class]);
            return response()->json(['message' => 'A végleges e-mail kézbesítése sikertelen volt. A projekt állapota változatlan.'], 503);
        }
        $quoteRequest->forceFill(['status' => 'completed', 'completed_at' => now(), 'final_amount_huf' => $remaining])->save();
        AdminAudit::record($request, 'project_completed', 'quote_request', $quoteRequest->id);
        $message = $remaining > 0
            ? 'A projekt lezárva, a végleges e-mail elküldve. A kód a fennmaradó összeg beérkezése után adható át.'
            : 'A projekt lezárva, a végleges e-mail elküldve. Nincs fennmaradó összeg, a kód átadható.';
        return response()->json(['message' => $message, 'data' => $quoteRequest->fresh()]);
    }

    /** A fennmaradó összeg kézi jóváírása (banki átutalás) – bevételként is rögzül a Pénzügyekben. */
    public function markFinalPaid(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $data = $request->validate(['paid_on' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:today']]);
        $amount = $quoteRequest->remainingAmount();
        if ($quoteRequest->status !== 'completed' || ! $quoteRequest->completed_at || $amount <= 0) {
            throw ValidationException::withMessages(['final' => 'Ehhez a projekthez nincs fizetendő fennmaradó összeg.']);
        }
        if ($quoteRequest->final_paid_at) {
            throw ValidationException::withMessages(['final' => 'A fennmaradó összeg már be van jelölve.']);
        }
        $date = $data['paid_on'] ?? now()->toDateString();
        $quoteRequest->forceFill(['final_paid_at' => $date.' '.now()->format('H:i:s'), 'final_paid_via' => 'transfer'])->save();
        // Átutalással rendezve: a nyitott kártyás fizetési oldal ne maradjon fizethető (dupla fizetés ellen).
        \App\Support\DepositCheckout::expireOpen($quoteRequest, 'final');
        \App\Models\FinanceEntry::create([
            'type' => 'income', 'occurred_on' => $date, 'amount_huf' => $amount,
            'vat_rate' => \App\Models\AdminSetting::finance()['vat_registered'] ? 27 : 0,
            'category' => 'Végszámla', 'description' => 'Fennmaradó összeg (átutalás) – #'.$quoteRequest->id.' '.$quoteRequest->customer_name,
            'quote_request_id' => $quoteRequest->id, 'source' => 'manual',
        ]);
        AdminAudit::record($request, 'final_marked_paid', 'quote_request', $quoteRequest->id);
        return response()->json(['message' => 'Fennmaradó összeg jóváírva – a kód átadható.', 'data' => $quoteRequest->fresh()]);
    }

    /**
     * Kód / átadási csomag átadása. Szerveroldalon zárolva, amíg a fennmaradó összeg nincs kifizetve:
     * az átadandó fájlokat az ügyfél csak ezután töltheti le, és ekkor kap róla e-mailt.
     */
    public function handover(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $data = $request->validate(['note' => ['sometimes', 'nullable', 'string', 'max:3000']]);
        if (! $quoteRequest->handoverUnlocked()) {
            throw ValidationException::withMessages(['handover' => $quoteRequest->status === 'completed'
                ? 'A kód csak a fennmaradó összeg ('.number_format($quoteRequest->remainingAmount(), 0, ',', ' ').' Ft) kifizetése után adható át.'
                : 'A kód átadása előtt jelöld késznek a projektet.']);
        }
        if ($quoteRequest->handed_over_at) {
            throw ValidationException::withMessages(['handover' => 'A kódot már átadtad ('.$quoteRequest->handed_over_at->timezone('Europe/Budapest')->format('Y. m. d.').').']);
        }
        if (! $quoteRequest->documents()->where('is_deliverable', true)->exists() && blank($data['note'] ?? null)) {
            throw ValidationException::withMessages(['handover' => 'Tölts fel legalább egy átadandó fájlt, vagy írd le az átadás módját (pl. GitHub-repó, tárhely-hozzáférés).']);
        }
        if (! \App\Support\Mailer::canSend()) {
            return response()->json(['message' => 'Valós e-mail-küldés szükséges az átadás értesítőjéhez.'], 503);
        }
        $url = $this->portalUrl($quoteRequest);
        $quoteRequest->handover_note = filled($data['note'] ?? null) ? trim($data['note']) : null;
        try {
            Mail::to($quoteRequest->email)->send(new ProjectHandedOver($quoteRequest, $url));
        } catch (\Throwable $exception) {
            Log::error('Project handover mail failed', ['quote_id' => $quoteRequest->id, 'exception_type' => $exception::class]);
            return response()->json(['message' => 'Az átadási e-mail kézbesítése sikertelen volt. Az átadás nem történt meg.'], 503);
        }
        $quoteRequest->forceFill(['handed_over_at' => now(), 'handover_note' => $quoteRequest->handover_note])->save();
        AdminAudit::record($request, 'project_handed_over', 'quote_request', $quoteRequest->id);
        return response()->json(['message' => 'A kód átadva, az ügyfél e-mailt kapott és letöltheti a fájlokat.', 'data' => $quoteRequest->fresh()]);
    }

    public function rotateClientAccess(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        if (! \App\Support\Mailer::canSend()) {
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
        // Átutalással rendezve: a nyitott kártyás fizetési oldal ne maradjon fizethető (dupla fizetés ellen).
        \App\Support\DepositCheckout::expireOpen($quoteRequest);
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
        $safe = [\App\Support\Csv::class, 'safe'];

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
