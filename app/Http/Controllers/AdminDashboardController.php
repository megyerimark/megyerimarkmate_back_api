<?php

namespace App\Http\Controllers;

use App\Models\AdminActivity;
use App\Models\PortfolioProject;
use App\Models\QuoteRequest;
use App\Models\Service;
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
            'recent_quotes' => QuoteRequest::latest()->limit(5)->get(),
            'quotes_monthly' => collect(range(5, 0))->map(function ($offset) {
                $month = now()->startOfMonth()->subMonths($offset);
                return [
                    'label' => $month->translatedFormat('M'),
                    'count' => QuoteRequest::whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count(),
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
