<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceAgreement;
use App\Models\QuoteRequest;
use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminMaintenanceController extends Controller
{
    private const QUOTE_FIELDS = ['id', 'customer_name', 'email', 'company_name', 'status', 'wants_maintenance'];

    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'agreements' => MaintenanceAgreement::with(['quoteRequest' => fn ($q) => $q->select(self::QUOTE_FIELDS)])
                ->latest('id')->limit(200)->get(),
            'candidates' => QuoteRequest::where('wants_maintenance', true)
                ->whereDoesntHave('maintenanceAgreement')
                ->latest('created_at')->limit(100)->get(self::QUOTE_FIELDS),
        ]])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        $this->ensureEligible($data['quote_request_id'], $data['status'] ?? 'draft');
        $agreement = DB::transaction(function () use ($data) {
            $created = MaintenanceAgreement::create($data);
            return $created->load(['quoteRequest' => fn ($q) => $q->select(self::QUOTE_FIELDS)]);
        });
        AdminAudit::record($request, 'created', 'maintenance_agreement', $agreement->id);
        return response()->json(['data' => $agreement, 'message' => 'Karbantartási megállapodás rögzítve.'], 201);
    }

    public function update(Request $request, MaintenanceAgreement $maintenanceAgreement): JsonResponse
    {
        $data = $request->validate($this->rules(false));
        if ($data === []) throw ValidationException::withMessages(['agreement' => 'Nincs módosítandó adat.']);
        $this->ensureEligible($maintenanceAgreement->quote_request_id, $data['status'] ?? $maintenanceAgreement->status);
        $maintenanceAgreement->update($data);
        AdminAudit::record($request, 'updated', 'maintenance_agreement', $maintenanceAgreement->id);
        return response()->json(['data' => $maintenanceAgreement->fresh()
            ->load(['quoteRequest' => fn ($q) => $q->select(self::QUOTE_FIELDS)])]);
    }

    /** Online előfizetés lemondása a jelenlegi számlázási időszak végével (a már kifizetett hónap még jár). */
    public function cancelSubscription(Request $request, MaintenanceAgreement $maintenanceAgreement): JsonResponse
    {
        if (! $maintenanceAgreement->stripe_subscription_id || ! \App\Support\Stripe::enabled()) {
            throw ValidationException::withMessages(['subscription' => 'Ehhez a megállapodáshoz nincs online előfizetés.']);
        }
        try {
            $sub = \App\Support\Stripe::post('subscriptions/'.$maintenanceAgreement->stripe_subscription_id, ['cancel_at_period_end' => 'true']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Stripe cancel failed', ['agreement_id' => $maintenanceAgreement->id, 'error' => $e->getMessage()]);
            throw ValidationException::withMessages(['subscription' => 'A Stripe most nem érhető el, próbáld újra később.']);
        }
        $maintenanceAgreement->forceFill(['stripe_status' => ($sub['status'] ?? 'active').'_cancel_scheduled', 'billing_enabled' => false])->save();
        AdminAudit::record($request, 'subscription_cancel_scheduled', 'maintenance_agreement', $maintenanceAgreement->id);
        return response()->json(['message' => 'Az előfizetés a jelenlegi időszak végén megszűnik.', 'data' => $maintenanceAgreement->fresh()
            ->load(['quoteRequest' => fn ($q) => $q->select(self::QUOTE_FIELDS)])]);
    }

    private function ensureEligible(int $quoteId, string $status): void
    {
        if ($status !== 'active') return;
        $quote = QuoteRequest::findOrFail($quoteId);
        if (! in_array($quote->status, ['accepted', 'completed'], true) || ! $quote->proposal_accepted_at) {
            throw ValidationException::withMessages(['status' => 'Aktiválás csak ténylegesen elfogadott ajánlathoz lehetséges.']);
        }
    }

    private function rules(bool $create): array
    {
        return [
            'quote_request_id' => $create
                ? ['required', 'integer', 'exists:quote_requests,id', 'unique:maintenance_agreements,quote_request_id']
                : ['prohibited'],
            'plan_name' => [$create ? 'required' : 'sometimes', 'string', 'min:2', 'max:100'],
            'monthly_fee_huf' => [$create ? 'required' : 'sometimes', 'integer', 'min:0', 'max:1000000'],
            'status' => [$create ? 'required' : 'sometimes', Rule::in(['draft', 'active', 'paused', 'canceled'])],
            'starts_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'next_review_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'included_services' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'billing_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
