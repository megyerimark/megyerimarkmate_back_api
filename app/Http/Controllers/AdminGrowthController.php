<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use App\Models\QuoteRequest;
use App\Models\SiteEvent;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * „Növekedés” nézet: honnan jönnek az érdeklődők, mennyit érnek, és kire kell ma válaszolni.
 * Minden szám a saját adatbázisból származik; becslést vagy kitalált értéket nem adunk vissza.
 */
class AdminGrowthController extends Controller
{
    private const WON_SQL = "(proposal_accepted_at IS NOT NULL OR status IN ('accepted','completed'))";

    public function index(): JsonResponse
    {
        $since30 = now()->subDays(30)->startOfDay();
        $since90 = now()->subDays(90)->startOfDay();

        $events = SiteEvent::where('created_at', '>=', $since30)
            ->select('event', DB::raw('COUNT(*) AS total'))->groupBy('event')->pluck('total', 'event');
        $homeVisits = SiteEvent::where('created_at', '>=', $since30)->where('event', 'page_view')->where('path', '/')->count();
        $submissions = QuoteRequest::where('created_at', '>=', $since30)->count();

        $funnel = [
            'visits' => $homeVisits,
            'page_views' => (int) ($events['page_view'] ?? 0),
            'cta_clicks' => (int) ($events['cta_click'] ?? 0),
            'quote_starts' => (int) ($events['quote_start'] ?? 0) + (int) ($events['quiz_start'] ?? 0),
            'submissions' => $submissions,
            // Csak akkor adunk arányt, ha volt mérhető látogatás – különben a szám félrevezető lenne.
            'visit_to_lead_percent' => $homeVisits > 0 ? round($submissions / $homeVisits * 100, 1) : null,
        ];

        $channels = QuoteRequest::query()->where('created_at', '>=', $since90)
            ->selectRaw("COALESCE(NULLIF(utm_source, ''), NULLIF(referrer_host, ''), 'direkt / ismeretlen') AS channel")
            ->selectRaw('COUNT(*) AS leads')
            ->selectRaw('SUM(CASE WHEN '.self::WON_SQL.' THEN 1 ELSE 0 END) AS won')
            ->selectRaw('COALESCE(SUM(CASE WHEN proposal_accepted_at IS NOT NULL THEN quoted_price_huf ELSE 0 END), 0) AS won_value')
            ->groupBy('channel')->orderByDesc('leads')->limit(10)->get()
            ->map(fn ($row) => [
                'channel' => $row->channel,
                'leads' => (int) $row->leads,
                'won' => (int) $row->won,
                'won_value_huf' => (int) $row->won_value,
            ])->values();

        $budgets = QuoteRequest::query()->where('created_at', '>=', $since90)
            ->selectRaw("COALESCE(NULLIF(budget_range, ''), 'nincs megadva') AS budget")
            ->selectRaw('COUNT(*) AS total')->groupBy('budget')->orderByDesc('total')->get()
            ->map(fn ($row) => ['label' => $row->budget, 'count' => (int) $row->total])->values();

        // Reakcióidő: mennyi idő telik el a beérkezés és az első válasz között.
        $responded = QuoteRequest::whereNotNull('first_response_at')->where('created_at', '>=', $since90)
            ->latest('created_at')->limit(500)->get(['created_at', 'first_response_at']);
        $avgHours = $responded->isEmpty() ? null : round(
            $responded->avg(fn ($quote) => max(0, $quote->created_at->diffInMinutes($quote->first_response_at, false))) / 60, 1
        );

        $waiting = QuoteRequest::where('status', 'new')->whereNull('first_response_at')
            ->where('created_at', '<=', now()->subHours(24))->orderBy('created_at')->limit(8)
            ->get(['id', 'customer_name', 'company_name', 'created_at'])
            ->map(fn ($quote) => [
                'id' => $quote->id,
                'customer_name' => $quote->customer_name,
                'company_name' => $quote->company_name,
                'created_at' => $quote->created_at,
                'hours_waiting' => (int) $quote->created_at->diffInHours(now()),
            ])->values();

        $followUps = QuoteRequest::whereNotNull('follow_up_at')->whereDate('follow_up_at', '<=', today())
            ->whereNotIn('status', ['rejected', 'completed'])->orderBy('follow_up_at')->limit(8)
            ->get(['id', 'customer_name', 'company_name', 'status', 'follow_up_at']);

        $staleProposals = QuoteRequest::where('status', 'quoted')->whereNull('proposal_accepted_at')
            ->where('quoted_at', '<=', now()->subDays(5))->orderBy('quoted_at')->limit(8)
            ->get(['id', 'customer_name', 'company_name', 'quoted_price_huf', 'quoted_at', 'proposal_valid_until']);

        $expiring = QuoteRequest::where('status', 'quoted')->whereNull('proposal_accepted_at')
            ->whereDate('proposal_valid_until', '>=', today())->whereDate('proposal_valid_until', '<=', today()->addDays(3))
            ->orderBy('proposal_valid_until')->limit(8)
            ->get(['id', 'customer_name', 'company_name', 'quoted_price_huf', 'proposal_valid_until']);

        $daily = collect(range(29, 0))->mapWithKeys(fn ($offset) => [now()->subDays($offset)->toDateString() => 0]);
        QuoteRequest::where('created_at', '>=', $since30)->pluck('created_at')
            ->each(function ($createdAt) use (&$daily) {
                $key = $createdAt->toDateString();
                if ($daily->has($key)) {
                    $daily[$key] = $daily[$key] + 1;
                }
            });

        $decided = QuoteRequest::whereIn('status', ['accepted', 'completed', 'rejected'])->count();
        $wonCount = QuoteRequest::whereRaw(self::WON_SQL)->count();
        $acceptedQuery = QuoteRequest::whereNotNull('proposal_accepted_at')->whereNotNull('quoted_price_huf');

        return response()->json(['data' => [
            'funnel' => $funnel,
            'channels' => $channels,
            'budgets' => $budgets,
            'daily_leads' => $daily->map(fn ($count, $date) => ['date' => $date, 'count' => $count])->values(),
            'response' => [
                'average_hours' => $avgHours,
                'responded_count' => $responded->count(),
                'waiting_over_24h' => $waiting,
            ],
            'attention' => [
                'follow_ups_due' => $followUps,
                'stale_proposals' => $staleProposals,
                'expiring_proposals' => $expiring,
            ],
            'revenue' => [
                'pipeline_value_huf' => (int) QuoteRequest::where('status', 'quoted')->whereNull('proposal_accepted_at')->sum('quoted_price_huf'),
                'average_deal_huf' => $acceptedQuery->count() ? (int) round($acceptedQuery->avg('quoted_price_huf')) : null,
                // Lezárt (nyert vagy elutasított) ügyekből számolt arány; függő ügyeket nem számolunk bele.
                'win_rate_percent' => $decided > 0 ? (int) round($wonCount / $decided * 100) : null,
            ],
            'trust' => [
                'testimonials_published' => Testimonial::where('is_published', true)->count(),
                'projects_published' => PortfolioProject::where('is_published', true)->where('is_demo', false)->count(),
            ],
        ]]);
    }
}
