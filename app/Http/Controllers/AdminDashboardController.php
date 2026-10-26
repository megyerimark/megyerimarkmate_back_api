<?php

namespace App\Http\Controllers;

use App\Models\AdminActivity;
use App\Models\ClientSite;
use App\Models\MaintenanceAgreement;
use App\Models\PortfolioProject;
use App\Models\ProjectMessage;
use App\Models\QuoteRequest;
use App\Models\Service;
use App\Models\StudioTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'quotes_total' => QuoteRequest::count(),
            'quotes_new' => QuoteRequest::where('status', 'new')->count(),
            'quotes_reviewing' => QuoteRequest::where('status', 'reviewing')->count(),
            'quotes_accepted' => QuoteRequest::where('status', 'accepted')->count(),
            'quotes_last_30_days' => QuoteRequest::where('created_at', '>=', now()->subDays(30))->count(),
            'clients_total' => QuoteRequest::distinct()->count('email'),
            'portfolio_total' => PortfolioProject::count(),
            'services_total' => Service::count(),
            'tasks_open' => StudioTask::where('is_done', false)->count(),
            'messages_unread' => ProjectMessage::unreadFromClient()->count(),
            'sites_expiring' => ClientSite::expiringWithin(30)->get(['id', 'name', 'domain', 'domain_expires_on', 'hosting_expires_on', 'ssl_expires_on']),
            'unread_threads' => ProjectMessage::unreadFromClient()
                ->selectRaw('quote_request_id, COUNT(*) as unread, MAX(created_at) as last_at')
                ->groupBy('quote_request_id')->orderByDesc('last_at')->limit(10)->get()
                ->map(fn ($row) => [
                    'quote_request_id' => (int) $row->quote_request_id,
                    'customer_name' => QuoteRequest::whereKey($row->quote_request_id)->value('customer_name'),
                    'unread' => (int) $row->unread,
                    'last_at' => $row->last_at,
                ])->values(),
            'tasks_overdue' => StudioTask::where('is_done', false)->whereDate('due_date', '<', today())->count(),
            'quotes_quoted' => QuoteRequest::where('status', 'quoted')->count(),
            'quotes_completed' => QuoteRequest::where('status', 'completed')->count(),
            'accepted_proposals_value_huf' => (int) QuoteRequest::whereNotNull('proposal_accepted_at')->sum('quoted_price_huf'),
            'maintenance_leads' => QuoteRequest::where('wants_maintenance', true)->count(),
            'maintenance_active' => MaintenanceAgreement::where('status', 'active')->count(),
            // Signed monthly contract values, NOT received Stripe/bank payments.
            'maintenance_contract_value_huf' => (int) MaintenanceAgreement::where('status', 'active')->sum('monthly_fee_huf'),
            'maintenance_reviews_due' => MaintenanceAgreement::where('status', 'active')->whereNotNull('next_review_on')
                ->whereDate('next_review_on', '<=', today())->count(),
            'quotes_by_status' => collect(['new', 'reviewing', 'quoted', 'accepted', 'rejected', 'completed'])
                ->map(fn ($status) => ['status' => $status, 'count' => QuoteRequest::where('status', $status)->count()])->values(),
            'quotes_by_type' => QuoteRequest::select('website_type', DB::raw('COUNT(*) AS total'))
                ->groupBy('website_type')->orderByDesc('total')->limit(6)->get()
                ->map(fn ($row) => ['label' => $row->website_type, 'count' => (int) $row->total]),
            'lead_sources' => [
                'quiz' => QuoteRequest::where('project_description', 'like', 'AURORA KVÍZ – IGÉNYFELMÉRÉS%')->count(),
                'form' => QuoteRequest::where('project_description', 'not like', 'AURORA KVÍZ – IGÉNYFELMÉRÉS%')->count(),
            ],
            'recent_quotes' => QuoteRequest::latest()->limit(5)->get(),
            'upcoming_tasks' => StudioTask::where('is_done', false)->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date')->limit(5)->get(),
            'quotes_monthly' => collect(range(5, 0))->map(function ($offset) {
                $month = now()->startOfMonth()->subMonths($offset);
                return [
                    'label' => $month->translatedFormat('M'),
                    'count' => QuoteRequest::whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count(),
                ];
            })->values(),
            'accepted_value_monthly' => collect(range(5, 0))->map(function ($offset) {
                $month = now()->startOfMonth()->subMonths($offset);
                return [
                    'label' => $month->translatedFormat('M'),
                    'value' => (int) QuoteRequest::whereBetween('proposal_accepted_at',
                        [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('quoted_price_huf'),
                ];
            })->values(),
        ]]);
    }

    public function clients(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        // No duplicate client PII table: customer history is derived from enquiries.
        $query = QuoteRequest::query()
            ->select('email')
            ->selectRaw('MAX(customer_name) AS customer_name')
            ->selectRaw('MAX(company_name) AS company_name')
            ->selectRaw('COUNT(*) AS quote_count')
            ->selectRaw('MAX(created_at) AS last_contact_at')
            ->groupBy('email');

        if (! empty($filters['q'])) {
            $search = addcslashes(trim($filters['q']), '%_\\');
            $query->where(function ($builder) use ($search) {
                $builder->where('email', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->orderByDesc('last_contact_at')
            ->paginate((int) ($filters['per_page'] ?? 15)));
    }

    public function clientQuotes(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:254']]);
        return response()->json(QuoteRequest::where('email', $data['email'])
            ->latest()->paginate(15));
    }

    public function activities(): JsonResponse
    {
        return response()->json(AdminActivity::query()->latest('created_at')->latest('id')->paginate(25));
    }
}
