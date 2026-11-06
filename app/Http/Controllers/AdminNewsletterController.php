<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Support\AdminAudit;
use App\Support\Brevo;
use App\Support\NewsletterSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminNewsletterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $items = NewsletterSubscriber::query()
            ->when($q !== '', fn ($query) => $query->where('email', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')->limit(500)
            ->get(['id', 'email', 'status', 'lang', 'source', 'consent_ip', 'confirmed_at', 'unsubscribed_at', 'created_at', 'brevo_synced_at', 'brevo_error', 'welcome_status']);

        $counts = NewsletterSubscriber::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return response()->json(['data' => $items, 'counts' => [
            'confirmed' => (int) ($counts['confirmed'] ?? 0), 'pending' => (int) ($counts['pending'] ?? 0), 'unsubscribed' => (int) ($counts['unsubscribed'] ?? 0),
        ], 'brevo' => $this->brevoStatus()])->header('Cache-Control', 'no-store');
    }

    private function brevoStatus(): array
    {
        $waiting = NewsletterSubscriber::whereIn('status', ['confirmed', 'unsubscribed'])->whereNull('brevo_synced_at');
        return [
            'configured' => Brevo::configured(),
            'welcome_list' => Brevo::welcomeListId() > 0,
            'waiting' => (clone $waiting)->count(),
            'errors' => (clone $waiting)->whereNotNull('brevo_error')->count(),
            'last_pull_at' => NewsletterSync::lastPullAt(),
        ];
    }

    /** Kézi szinkron: a várakozók feltöltése és a Brevóban leiratkozottak teljes lehúzása. */
    public function sync(Request $request): JsonResponse
    {
        if (! Brevo::configured()) {
            throw ValidationException::withMessages(['brevo' => 'A Brevo nincs beállítva (BREVO_API_KEY, BREVO_LIST_ID).']);
        }
        try {
            $result = NewsletterSync::run(full: true);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['brevo' => mb_substr($e->getMessage(), 0, 300)]);
        }
        AdminAudit::record($request, 'synced', 'newsletter_brevo');
        return response()->json([
            'message' => "Brevo-szinkron kész: {$result['synced']} feltöltve, {$result['failed']} hibás, {$result['unsubscribed']} leiratkozás átvezetve.",
            'result' => $result, 'brevo' => $this->brevoStatus(),
        ]);
    }

    /**
     * Korábbi ügyfelek importja – CSAK igazolható marketing-hozzájárulással (a forrását rögzítjük).
     * Az importáltak nem kapják meg az üdvözlő sorozatot, a már leiratkozottak pedig kimaradnak.
     */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'emails' => ['required', 'string', 'max:100000'],
            'lang' => ['required', Rule::in(['hu', 'en'])],
            'consent_note' => ['required', 'string', 'min:10', 'max:300'],
            'confirm' => ['accepted'],
        ]);
        $candidates = array_values(array_unique(array_filter(array_map(
            fn ($e) => Str::lower(trim($e, " \t\n\r\0\x0B\"'<>;,")),
            preg_split('/[\s,;]+/', $data['emails']) ?: []
        ))));
        if (count($candidates) > 2000) {
            throw ValidationException::withMessages(['emails' => 'Egyszerre legfeljebb 2000 címet importálj.']);
        }
        $counts = ['added' => 0, 'already' => 0, 'unsubscribed' => 0, 'invalid' => 0];
        $consent = mb_substr('Import (admin): '.trim($data['consent_note']), 0, 500);
        foreach ($candidates as $email) {
            if (strlen($email) > 254 || Validator::make(['e' => $email], ['e' => 'email:rfc'])->fails()) { $counts['invalid']++; continue; }
            $existing = NewsletterSubscriber::where('email', $email)->first();
            if ($existing?->status === 'unsubscribed') { $counts['unsubscribed']++; continue; } // leiratkozott nem kerül vissza
            if ($existing?->status === 'confirmed') { $counts['already']++; continue; }
            $subscriber = $existing ?? new NewsletterSubscriber(['email' => $email]);
            $subscriber->fill(['status' => 'confirmed', 'lang' => $data['lang'], 'source' => 'import', 'consent_text' => $consent, 'consent_ip' => null, 'consent_user_agent' => null]);
            $subscriber->confirm_token_hash = null;
            $subscriber->unsubscribe_token ??= bin2hex(random_bytes(32));
            $subscriber->confirmed_at = now();
            $subscriber->welcome_status = 'skipped';
            $subscriber->brevo_unblock = false;
            $subscriber->brevo_synced_at = null;
            $subscriber->save();
            $counts['added']++;
        }
        AdminAudit::record($request, 'imported', 'newsletter_subscribers');

        if ($counts['added'] > 0 && Brevo::configured()) {
            // feltöltés, majd teljes lehúzás: aki a Brevóban már leiratkozott, itt is leiratkozott lesz
            \Illuminate\Support\defer(function () {
                try { NewsletterSync::run(full: true); } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('Brevo import sync failed', ['error' => $e->getMessage()]); }
            });
        }
        return response()->json(['message' => "Import kész: {$counts['added']} új, {$counts['already']} már feliratkozott, {$counts['unsubscribed']} leiratkozott (kihagyva), {$counts['invalid']} érvénytelen.", 'counts' => $counts]);
    }

    /** Végleges törlés (GDPR törlési kérelem). */
    public function destroy(Request $request, NewsletterSubscriber $subscriber): JsonResponse
    {
        $email = $subscriber->email;
        $subscriber->delete();
        NewsletterSync::forgetLater($email);
        AdminAudit::record($request, 'deleted', 'newsletter_subscriber', $subscriber->id);
        return response()->json(['message' => 'Feliratkozó véglegesen törölve.']);
    }

    /** CSV-export csak a megerősített címekről (Excelben ékezethelyesen nyílik). */
    public function export(Request $request): StreamedResponse
    {
        AdminAudit::record($request, 'exported', 'newsletter_subscribers');
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['email', 'nyelv', 'forrás', 'megerősítve'], ';');
            NewsletterSubscriber::where('status', 'confirmed')->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    // Képlet-injektálás ellen (Excel): a =, +, -, @ kezdetű cellák elé aposztróf kerül.
                    fputcsv($out, array_map([\App\Support\Csv::class, 'safe'], [$row->email, $row->lang, $row->source, $row->confirmed_at?->timezone('Europe/Budapest')->format('Y-m-d H:i')]), ';');
                }
            });
            fclose($out);
        }, 'hirlevel-feliratkozok-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
