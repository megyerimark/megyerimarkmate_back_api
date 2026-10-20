<?php

namespace App\Http\Controllers;

use App\Models\QuoteRequest;
use App\Support\AdminAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'quoted_price_huf' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999999999'],
        ]);

        if ($data === []) {
            return response()->json(['message' => 'Nincs módosítandó adat.'], 422);
        }

        $oldStatus = $quoteRequest->status;
        foreach (['status', 'admin_notes', 'quoted_price_huf'] as $key) {
            if (array_key_exists($key, $data)) {
                $quoteRequest->{$key} = $data[$key];
            }
        }
        if ($oldStatus !== 'quoted' && $quoteRequest->status === 'quoted') {
            $quoteRequest->quoted_at = now();
        }
        $quoteRequest->save();
        AdminAudit::record($request, 'updated', 'quote_request', $quoteRequest->id);

        return response()->json([
            'message' => 'Az ajánlatkérés frissítve.',
            'data' => $quoteRequest->fresh(),
        ]);
    }

    public function destroy(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $id = $quoteRequest->id;
        $quoteRequest->delete();
        AdminAudit::record($request, 'deleted', 'quote_request', $id);
        return response()->json(['message' => 'Az ajánlatkérés véglegesen törölve.']);
    }
}
