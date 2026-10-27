<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            ->get(['id', 'email', 'status', 'lang', 'source', 'consent_ip', 'confirmed_at', 'unsubscribed_at', 'created_at']);

        $counts = NewsletterSubscriber::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return response()->json(['data' => $items, 'counts' => [
            'confirmed' => (int) ($counts['confirmed'] ?? 0), 'pending' => (int) ($counts['pending'] ?? 0), 'unsubscribed' => (int) ($counts['unsubscribed'] ?? 0),
        ]])->header('Cache-Control', 'no-store');
    }

    /** Végleges törlés (GDPR törlési kérelem). */
    public function destroy(Request $request, NewsletterSubscriber $subscriber): JsonResponse
    {
        $subscriber->delete();
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
                    fputcsv($out, [$row->email, $row->lang, $row->source, $row->confirmed_at?->timezone('Europe/Budapest')->format('Y-m-d H:i')], ';');
                }
            });
            fclose($out);
        }, 'hirlevel-feliratkozok-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
