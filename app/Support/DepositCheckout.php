<?php

namespace App\Support;

use App\Models\QuoteRequest;

/** Előleg-fizetés Stripe Checkout munkamenet – az elfogadáskor és a „Fizetés” gombnál is ez készül. */
class DepositCheckout
{
    /** Fizetendő-e most online előleg ennél az ajánlatnál. */
    public static function due(QuoteRequest $quote): bool
    {
        return Stripe::enabled() && $quote->status === 'accepted' && $quote->proposal_accepted_at
            && $quote->depositAmount() && ! $quote->deposit_paid_at;
    }

    /** @return string A Stripe által hosztolt fizetési oldal URL-je. */
    public static function create(QuoteRequest $quote): string
    {
        $base = config('portfolio.frontend_url');
        $session = Stripe::post('checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $base.'/fizetes/sikeres',
            'cancel_url' => $base.'/fizetes/megszakitva',
            'customer_email' => $quote->email,
            'client_reference_id' => 'quote-'.$quote->id,
            'locale' => 'hu',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => config('services.stripe.currency'),
                    'unit_amount' => Stripe::huf($quote->depositAmount()),
                    'product_data' => ['name' => 'Előleg – #'.$quote->id.' webfejlesztési projekt ('.$quote->deposit_percent.'%)'],
                ],
            ]],
            'metadata' => ['type' => 'deposit', 'quote_request_id' => $quote->id],
            'payment_intent_data' => ['metadata' => ['type' => 'deposit', 'quote_request_id' => $quote->id]],
        ]);
        return $session['url'];
    }
}
