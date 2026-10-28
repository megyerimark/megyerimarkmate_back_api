<?php

namespace App\Support;

use App\Models\QuoteRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Előleg- és végösszeg-fizetés Stripe Checkout munkamenete – az elfogadáskor, a lezáráskor és a „Fizetés” gombnál is ez készül.
 * A $kind 'deposit' (előleg) vagy 'final' (projektlezárás utáni fennmaradó összeg); mindkettő a saját
 * `{kind}_checkout_*` és `{kind}_paid_at` oszlopait használja.
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

    /** Fizetendő-e most online a lezárt projekt fennmaradó összege. */
    public static function finalDue(QuoteRequest $quote): bool
    {
        return Stripe::enabled() && $quote->status === 'completed' && $quote->completed_at
            && $quote->remainingAmount() > 0 && ! $quote->final_paid_at;
    }

    private static function amount(QuoteRequest $quote, string $kind): int
    {
        return $kind === 'final' ? $quote->remainingAmount() : (int) $quote->depositAmount();
    }

    /**
     * @return string A Stripe által hosztolt fizetési oldal URL-je.
     * @throws DepositPaymentPending ha az előző fizetés már sikerült, csak a webhook még nem érkezett meg.
     */
    public static function create(QuoteRequest $quote, string $kind = 'deposit'): string
    {
        return Cache::lock($kind.'-checkout:'.$quote->id, 20)->block(15, function () use ($quote, $kind) {
            $quote->refresh();
            if ($quote->{$kind.'_paid_at'}) {
                throw new DepositPaymentPending('Ez az összeg már be van fizetve.');
            }
            if ($url = self::reusable($quote, $kind)) {
                return $url;
            }
            return self::open($quote, $kind);
        });
    }

    /** Lezárja a nyitott fizetési oldalt (pl. ha az előleget átutalással rendezték). Hibát nem dob. */
    public static function expireOpen(QuoteRequest $quote, string $kind = 'deposit'): void
    {
        self::expireRemote($quote, $kind);
        self::forget($quote, $kind);
    }

    private static function expireRemote(QuoteRequest $quote, string $kind): void
    {
        $sessionId = $quote->{$kind.'_checkout_session_id'};
        if (! $sessionId || ! Stripe::enabled()) {
            return;
        }
        try {
            Stripe::post('checkout/sessions/'.rawurlencode($sessionId).'/expire');
        } catch (Throwable $e) {
            // Már lejárt / kifizetett munkamenetnél a Stripe hibát ad – ez rendben van.
            Log::info('Checkout expire skipped', ['quote_id' => $quote->id, 'kind' => $kind, 'error' => $e->getMessage()]);
        }
    }

    /** A tárolt munkamenet adatainak törlése (fizetés vagy lezárás után). */
    public static function forget(QuoteRequest $quote, string $kind = 'deposit'): void
    {
        $quote->forceFill([
            $kind.'_checkout_session_id' => null, $kind.'_checkout_url' => null, $kind.'_checkout_expires_at' => null,
        ])->save();
    }

    /** A még nyitott, változatlan összegű munkamenet URL-je, ha van. */
    private static function reusable(QuoteRequest $quote, string $kind): ?string
    {
        $sessionId = $quote->{$kind.'_checkout_session_id'};
        $url = $quote->{$kind.'_checkout_url'};
        $expires = $quote->{$kind.'_checkout_expires_at'};
        if (! $sessionId || ! $url) {
            return null;
        }
        if ($expires && $expires->lte(now()->addMinutes(5))) {
            return null;
        }
        try {
            $session = Stripe::get('checkout/sessions/'.rawurlencode($sessionId));
        } catch (Throwable $e) {
            // A Stripe épp nem elérhető: a tárolt (még érvényes) oldal biztonságosabb, mint egy új nyitása.
            Log::warning('Checkout lookup failed, reusing stored URL', ['quote_id' => $quote->id, 'kind' => $kind, 'error' => $e->getMessage()]);
            return $url;
        }
        $status = $session['status'] ?? null;
        if ($status === 'complete' || ($session['payment_status'] ?? null) === 'paid') {
            throw new DepositPaymentPending('A fizetés már megtörtént, a visszaigazolás feldolgozás alatt.');
        }
        if ($status !== 'open') {
            return null;
        }
        if ((int) ($session['amount_total'] ?? 0) !== Stripe::huf(self::amount($quote, $kind))) {
            // Az összeg közben módosult: a régi összegű oldal ne maradjon fizethető.
            // (A munkamenet-azonosító megmarad: ebből készül az új Idempotency-Key.)
            self::expireRemote($quote, $kind);
            return null;
        }
        return $url;
    }

    private static function open(QuoteRequest $quote, string $kind): string
    {
        $base = config('portfolio.frontend_url');
        $amount = self::amount($quote, $kind);
        $name = $kind === 'final'
            ? 'Fennmaradó összeg – #'.$quote->id.' webfejlesztési projekt'
            : 'Előleg – #'.$quote->id.' webfejlesztési projekt ('.$quote->deposit_percent.'%)';
        // Determinisztikus kulcs: ugyanabból az állapotból (előző munkamenet + összeg) indított kérések
        // ugyanazt a munkamenetet kapják a Stripe-tól, még ha a zár valamiért nem is védene.
        $key = $kind.'-'.hash('sha256', implode('|', [$quote->id, $amount, $quote->{$kind.'_checkout_session_id'} ?? 'first']));
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
                    'product_data' => ['name' => $name],
                ],
            ]],
            'metadata' => ['type' => $kind, 'quote_request_id' => $quote->id],
            'payment_intent_data' => ['metadata' => ['type' => $kind, 'quote_request_id' => $quote->id]],
        ], $key);

        $quote->forceFill([
            $kind.'_checkout_session_id' => $session['id'] ?? null,
            $kind.'_checkout_url' => $session['url'],
            // A Stripe alapból 24 óráig tartja nyitva; ha nem jön vissza a lejárat, óvatosan számolunk.
            $kind.'_checkout_expires_at' => isset($session['expires_at'])
                ? Carbon::createFromTimestamp((int) $session['expires_at']) : now()->addHours(23),
        ])->save();

        return $session['url'];
    }
}
