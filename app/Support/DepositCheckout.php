<?php

namespace App\Support;

use App\Models\QuoteRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Előleg-fizetés Stripe Checkout munkamenet – az elfogadáskor és a „Fizetés” gombnál is ez készül.
 *
 * Dupla fizetés elleni védelem:
 * - a nyitott munkamenetet eltároljuk, és amíg él, mindig UGYANAZT a fizetési oldalt adjuk vissza
 *   (több fül, dupla kattintás, újratöltés sem nyit új fizetést);
 * - a létrehozás zár alatt fut, és Idempotency-Key-t küld a Stripe-nak (párhuzamos kérések ellen);
 * - ha az összeg időközben változott, a régi munkamenetet lezárjuk, mielőtt újat nyitnánk.
 */
class DepositCheckout
{
    /** Fizetendő-e most online előleg ennél az ajánlatnál. */
    public static function due(QuoteRequest $quote): bool
    {
        return Stripe::enabled() && $quote->status === 'accepted' && $quote->proposal_accepted_at
            && $quote->depositAmount() && ! $quote->deposit_paid_at;
    }

    /**
     * @return string A Stripe által hosztolt fizetési oldal URL-je.
     * @throws DepositPaymentPending ha az előző fizetés már sikerült, csak a webhook még nem érkezett meg.
     */
    public static function create(QuoteRequest $quote): string
    {
        return Cache::lock('deposit-checkout:'.$quote->id, 20)->block(15, function () use ($quote) {
            $quote->refresh();
            if ($quote->deposit_paid_at) {
                throw new DepositPaymentPending('Az előleg már be van fizetve.');
            }
            if ($url = self::reusable($quote)) {
                return $url;
            }
            return self::open($quote);
        });
    }

    /** Lezárja a nyitott fizetési oldalt (pl. ha az előleget átutalással rendezték). Hibát nem dob. */
    public static function expireOpen(QuoteRequest $quote): void
    {
        self::expireRemote($quote);
        self::forget($quote);
    }

    private static function expireRemote(QuoteRequest $quote): void
    {
        if (! $quote->deposit_checkout_session_id || ! Stripe::enabled()) {
            return;
        }
        try {
            Stripe::post('checkout/sessions/'.rawurlencode($quote->deposit_checkout_session_id).'/expire');
        } catch (Throwable $e) {
            // Már lejárt / kifizetett munkamenetnél a Stripe hibát ad – ez rendben van.
            Log::info('Deposit checkout expire skipped', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
        }
    }

    /** A tárolt munkamenet adatainak törlése (fizetés vagy lezárás után). */
    public static function forget(QuoteRequest $quote): void
    {
        $quote->forceFill([
            'deposit_checkout_session_id' => null, 'deposit_checkout_url' => null, 'deposit_checkout_expires_at' => null,
        ])->save();
    }

    /** A még nyitott, változatlan összegű munkamenet URL-je, ha van. */
    private static function reusable(QuoteRequest $quote): ?string
    {
        if (! $quote->deposit_checkout_session_id || ! $quote->deposit_checkout_url) {
            return null;
        }
        if ($quote->deposit_checkout_expires_at && $quote->deposit_checkout_expires_at->lte(now()->addMinutes(5))) {
            return null;
        }
        try {
            $session = Stripe::get('checkout/sessions/'.rawurlencode($quote->deposit_checkout_session_id));
        } catch (Throwable $e) {
            // A Stripe épp nem elérhető: a tárolt (még érvényes) oldal biztonságosabb, mint egy új nyitása.
            Log::warning('Deposit checkout lookup failed, reusing stored URL', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
            return $quote->deposit_checkout_url;
        }
        $status = $session['status'] ?? null;
        if ($status === 'complete' || ($session['payment_status'] ?? null) === 'paid') {
            throw new DepositPaymentPending('A fizetés már megtörtént, a visszaigazolás feldolgozás alatt.');
        }
        if ($status !== 'open') {
            return null;
        }
        if ((int) ($session['amount_total'] ?? 0) !== Stripe::huf((int) $quote->depositAmount())) {
            // Az ajánlat összege közben módosult: a régi összegű oldal ne maradjon fizethető.
            // (A munkamenet-azonosító megmarad: ebből készül az új Idempotency-Key.)
            self::expireRemote($quote);
            return null;
        }
        return $quote->deposit_checkout_url;
    }

    private static function open(QuoteRequest $quote): string
    {
        $base = config('portfolio.frontend_url');
        $amount = (int) $quote->depositAmount();
        // Determinisztikus kulcs: ugyanabból az állapotból (előző munkamenet + összeg) indított kérések
        // ugyanazt a munkamenetet kapják a Stripe-tól, még ha a zár valamiért nem is védene.
        $key = 'deposit-'.hash('sha256', implode('|', [$quote->id, $amount, $quote->deposit_checkout_session_id ?? 'first']));
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
                    'unit_amount' => Stripe::huf($amount),
                    'product_data' => ['name' => 'Előleg – #'.$quote->id.' webfejlesztési projekt ('.$quote->deposit_percent.'%)'],
                ],
            ]],
            'metadata' => ['type' => 'deposit', 'quote_request_id' => $quote->id],
            'payment_intent_data' => ['metadata' => ['type' => 'deposit', 'quote_request_id' => $quote->id]],
        ], $key);

        $quote->forceFill([
            'deposit_checkout_session_id' => $session['id'] ?? null,
            'deposit_checkout_url' => $session['url'],
            // A Stripe alapból 24 óráig tartja nyitva; ha nem jön vissza a lejárat, óvatosan számolunk.
            'deposit_checkout_expires_at' => isset($session['expires_at'])
                ? Carbon::createFromTimestamp((int) $session['expires_at']) : now()->addHours(23),
        ])->save();

        return $session['url'];
    }
}
