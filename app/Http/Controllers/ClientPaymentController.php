<?php

namespace App\Http\Controllers;

use App\Models\QuoteRequest;
use App\Support\Stripe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Ügyféloldali online fizetés a privát projektlinkkel (a kulcs a kérés törzsében érkezik).
 * Kártyaadat sosem érinti a szervert: a fizetés a Stripe saját oldalán (Checkout) történik.
 */
class ClientPaymentController extends Controller
{
    private function quote(Request $request): QuoteRequest
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D']]);
        return \App\Support\ClientAccess::quote($data['token']);
    }

    private function ensureStripe(): void
    {
        if (! Stripe::enabled()) {
            throw ValidationException::withMessages(['payment' => 'Az online fizetés jelenleg nem elérhető. Átutalással is fizethetsz, a részleteket e-mailben küldöm.']);
        }
    }

    private function urls(): array
    {
        $base = config('portfolio.frontend_url');
        return ['success_url' => $base.'/fizetes/sikeres', 'cancel_url' => $base.'/fizetes/megszakitva'];
    }

    /** Előleg fizetése bankkártyával (egyszeri Checkout). */
    public function depositCheckout(Request $request): JsonResponse
    {
        $quote = $this->quote($request);
        $this->ensureStripe();
        $amount = $quote->depositAmount();
        if ($quote->status !== 'accepted' || ! $quote->proposal_accepted_at || ! $amount || $quote->deposit_paid_at) {
            throw ValidationException::withMessages(['payment' => 'Ehhez a projekthez jelenleg nincs fizetendő előleg.']);
        }
        try {
            $url = \App\Support\DepositCheckout::create($quote);
        } catch (\App\Support\DepositPaymentPending) {
            throw ValidationException::withMessages(['payment' => 'Az előleg befizetése már megtörtént, a visszaigazolás pár percen belül megjelenik. Nem kell újra fizetned.']);
        } catch (Throwable $e) {
            Log::error('Stripe deposit checkout failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
            throw ValidationException::withMessages(['payment' => 'A fizetési oldal most nem nyitható meg. Próbáld újra pár perc múlva.']);
        }
        return response()->json(['url' => $url])->header('Cache-Control', 'no-store');
    }

    /** A lezárt projekt fennmaradó összegének fizetése bankkártyával (egyszeri Checkout). */
    public function finalCheckout(Request $request): JsonResponse
    {
        $quote = $this->quote($request);
        $this->ensureStripe();
        if (! \App\Support\DepositCheckout::finalDue($quote)) {
            throw ValidationException::withMessages(['payment' => 'Ehhez a projekthez jelenleg nincs fizetendő fennmaradó összeg.']);
        }
        try {
            $url = \App\Support\DepositCheckout::create($quote, 'final');
        } catch (\App\Support\DepositPaymentPending) {
            throw ValidationException::withMessages(['payment' => 'A fennmaradó összeg befizetése már megtörtént, a visszaigazolás pár percen belül megjelenik. Nem kell újra fizetned.']);
        } catch (Throwable $e) {
            Log::error('Stripe final checkout failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
            throw ValidationException::withMessages(['payment' => 'A fizetési oldal most nem nyitható meg. Próbáld újra pár perc múlva.']);
        }
        return response()->json(['url' => $url])->header('Cache-Control', 'no-store');
    }

    /** Havi karbantartási előfizetés indítása (Checkout, subscription mód). */
    public function maintenanceCheckout(Request $request): JsonResponse
    {
        $quote = $this->quote($request);
        $this->ensureStripe();
        $agreement = $quote->maintenanceAgreement;
        if (! $agreement || ! $agreement->billing_enabled || $agreement->monthly_fee_huf < 175
            || in_array($agreement->stripe_status, ['active', 'trialing', 'past_due'], true)) {
            throw ValidationException::withMessages(['payment' => 'A karbantartási előfizetés jelenleg nem indítható.']);
        }
        try {
            $session = Stripe::post('checkout/sessions', $this->urls() + array_filter([
                'mode' => 'subscription',
                'customer' => $agreement->stripe_customer_id,
                'customer_email' => $agreement->stripe_customer_id ? null : $quote->email,
                'client_reference_id' => 'maintenance-'.$agreement->id,
                'locale' => 'hu',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => config('services.stripe.currency'),
                        'unit_amount' => Stripe::huf($agreement->monthly_fee_huf),
                        'recurring' => ['interval' => 'month'],
                        'product_data' => ['name' => 'Weboldal-karbantartás – '.$agreement->plan_name],
                    ],
                ]],
                'metadata' => ['type' => 'maintenance', 'maintenance_agreement_id' => $agreement->id],
                'subscription_data' => ['metadata' => ['type' => 'maintenance', 'maintenance_agreement_id' => $agreement->id]],
            ]));
        } catch (Throwable $e) {
            Log::error('Stripe maintenance checkout failed', ['agreement_id' => $agreement->id, 'error' => $e->getMessage()]);
            throw ValidationException::withMessages(['payment' => 'Az előfizetési oldal most nem nyitható meg. Próbáld újra pár perc múlva.']);
        }
        return response()->json(['url' => $session['url']])->header('Cache-Control', 'no-store');
    }

    /** Stripe ügyfélportál: kártyacsere, számlák, lemondás – az ügyfél maga intézheti. */
    public function billingPortal(Request $request): JsonResponse
    {
        $quote = $this->quote($request);
        $this->ensureStripe();
        $customer = $quote->maintenanceAgreement?->stripe_customer_id;
        if (! $customer) {
            throw ValidationException::withMessages(['payment' => 'Ehhez a projekthez még nincs online előfizetés.']);
        }
        try {
            $session = Stripe::post('billing_portal/sessions', ['customer' => $customer, 'return_url' => config('portfolio.frontend_url').'/fizetes/sikeres', 'locale' => 'hu']);
        } catch (Throwable $e) {
            Log::error('Stripe billing portal failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
            throw ValidationException::withMessages(['payment' => 'Az előfizetés-kezelő most nem érhető el.']);
        }
        return response()->json(['url' => $session['url']])->header('Cache-Control', 'no-store');
    }
}
