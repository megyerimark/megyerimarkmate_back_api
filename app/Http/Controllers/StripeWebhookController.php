<?php

namespace App\Http\Controllers;

use App\Mail\AdminPaymentNotice;
use App\Models\FinanceEntry;
use App\Models\MaintenanceAgreement;
use App\Models\QuoteRequest;
use App\Support\Stripe;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stripe webhook: a fizetések állapotát KIZÁRÓLAG innen, aláírt eseményekből frissítjük
 * (a böngészős visszairányítás hamisítható, ez nem).
 * Stripe Dashboard → Fejlesztők → Webhookok → végpont: https://<domain>/api/stripe/webhook
 * Események: checkout.session.completed, invoice.paid, invoice.payment_failed,
 *            customer.subscription.updated, customer.subscription.deleted
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        if (! Stripe::verifySignature($payload, $request->header('Stripe-Signature'))) {
            return response('Invalid signature', 400);
        }
        $event = json_decode($payload, true);
        if (! is_array($event) || empty($event['id']) || empty($event['type'])) {
            return response('Invalid payload', 400);
        }
        // Idempotencia: a Stripe ugyanazt az eseményt többször is elküldheti.
        $fresh = DB::table('stripe_events')->insertOrIgnore(['id' => $event['id'], 'type' => $event['type'], 'processed_at' => now()]);
        if (! $fresh) {
            return response('Already processed', 200);
        }
        try {
            $object = $event['data']['object'] ?? [];
            match ($event['type']) {
                'checkout.session.completed' => $this->checkoutCompleted($object),
                'invoice.paid' => $this->invoicePaid($object),
                'invoice.payment_failed' => $this->invoiceFailed($object),
                'customer.subscription.updated', 'customer.subscription.deleted' => $this->subscriptionChanged($object),
                default => null,
            };
        } catch (Throwable $e) {
            // Feldolgozási hiba: töröljük a jelölést, hogy a Stripe újrapróbálhassa.
            DB::table('stripe_events')->where('id', $event['id'])->delete();
            Log::error('Stripe webhook failed', ['event' => $event['id'], 'type' => $event['type'], 'error' => $e->getMessage()]);
            return response('Error', 500);
        }
        return response('OK', 200);
    }

    private function checkoutCompleted(array $session): void
    {
        $meta = $session['metadata'] ?? [];
        if (($meta['type'] ?? null) === 'deposit' && ($session['payment_status'] ?? null) === 'paid') {
            $amount = intdiv((int) ($session['amount_total'] ?? 0), 100);
            // Sorzárolás: két, egyszerre érkező webhook (két kifizetett munkamenet) közül csak az egyik lehet az „első”.
            [$quote, $duplicate] = DB::transaction(function () use ($meta) {
                $quote = QuoteRequest::whereKey($meta['quote_request_id'] ?? 0)->lockForUpdate()->first();
                if (! $quote) return [null, false];
                if ($quote->deposit_paid_at) return [$quote, true];
                $quote->forceFill([
                    'deposit_paid_at' => now(), 'deposit_paid_via' => 'stripe',
                    'deposit_checkout_session_id' => null, 'deposit_checkout_url' => null, 'deposit_checkout_expires_at' => null,
                ])->save();
                return [$quote, false];
            });
            if (! $quote) return;
            if ($duplicate && FinanceEntry::where('external_ref', 'stripe:'.$session['id'])->exists()) {
                return; // ugyanaz a munkamenet, csak más eseményazonosítóval érkezett újra
            }
            if ($duplicate) {
                // Az előleg már rendezve volt (másik fülön / átutalással), mégis befizettek egy újabbat.
                // A pénz beérkezett, ezért rögzítjük, és szólunk, hogy vissza kell téríteni.
                Log::warning('Duplicate deposit payment', ['quote_id' => $quote->id, 'session' => $session['id'] ?? null]);
                FinanceEntry::recordIncome('stripe:'.$session['id'], $amount, 'Előleg – dupla, visszatérítendő',
                    'DUPLA előleg – #'.$quote->id.' '.$quote->customer_name.' (visszatérítés után töröld)', $quote->id);
                $this->notify('FIGYELEM: dupla előlegfizetés – '.$quote->customer_name, [
                    'Projekt: #'.$quote->id, 'Összeg: '.number_format($amount, 0, ',', ' ').' Ft',
                    'Az előleg már korábban rendezve volt ('.($quote->deposit_paid_via === 'transfer' ? 'átutalással' : 'kártyával').').',
                    'Térítsd vissza a Stripe Dashboardon (Payments → a fizetés → Refund), majd töröld a tételt a Pénzügyekből.',
                ]);
                return;
            }
            FinanceEntry::recordIncome('stripe:'.$session['id'], $amount, 'Előleg', 'Előleg – #'.$quote->id.' '.$quote->customer_name, $quote->id);
            $this->notify('Előleg beérkezett: '.$quote->customer_name, [
                'Projekt: #'.$quote->id, 'Összeg: '.number_format($amount, 0, ',', ' ').' Ft', 'A munka megkezdhető.',
            ]);
            return;
        }
        if (($meta['type'] ?? null) === 'maintenance' && ! empty($session['subscription'])) {
            $agreement = MaintenanceAgreement::find($meta['maintenance_agreement_id'] ?? 0);
            if (! $agreement) return;
            $agreement->forceFill([
                'stripe_customer_id' => $session['customer'] ?? $agreement->stripe_customer_id,
                'stripe_subscription_id' => $session['subscription'],
                'stripe_status' => 'active',
                'status' => 'active',
                'starts_on' => $agreement->starts_on ?? now()->toDateString(),
            ])->save();
            $this->notify('Karbantartási előfizetés indult', [
                'Ügyfél: '.($agreement->quoteRequest?->customer_name ?? '#'.$agreement->quote_request_id),
                'Csomag: '.$agreement->plan_name, 'Havidíj: '.number_format($agreement->monthly_fee_huf, 0, ',', ' ').' Ft',
            ]);
        }
    }

    private function agreementForInvoice(array $invoice): ?MaintenanceAgreement
    {
        $subscription = $invoice['subscription'] ?? ($invoice['parent']['subscription_details']['subscription'] ?? null);
        return $subscription ? MaintenanceAgreement::where('stripe_subscription_id', $subscription)->first() : null;
    }

    private function invoicePaid(array $invoice): void
    {
        $agreement = $this->agreementForInvoice($invoice);
        if (! $agreement) return;
        $amount = intdiv((int) ($invoice['amount_paid'] ?? 0), 100);
        $periodEnd = $invoice['lines']['data'][0]['period']['end'] ?? null;
        $agreement->forceFill([
            'stripe_status' => 'active', 'last_payment_at' => now(),
            'current_period_end' => $periodEnd ? now()->setTimestamp($periodEnd) : $agreement->current_period_end,
        ])->save();
        if ($amount > 0) {
            FinanceEntry::recordIncome('stripe:'.$invoice['id'], $amount, 'Karbantartás',
                'Havidíj – '.$agreement->plan_name.' ('.($agreement->quoteRequest?->customer_name ?? '#'.$agreement->quote_request_id).')',
                $agreement->quote_request_id);
        }
    }

    private function invoiceFailed(array $invoice): void
    {
        $agreement = $this->agreementForInvoice($invoice);
        if (! $agreement) return;
        $agreement->forceFill(['stripe_status' => 'past_due'])->save();
        $this->notify('Sikertelen karbantartási terhelés', [
            'Ügyfél: '.($agreement->quoteRequest?->customer_name ?? '#'.$agreement->quote_request_id),
            'A Stripe automatikusan újrapróbálja; érdemes szólni az ügyfélnek, hogy frissítse a kártyáját.',
        ]);
    }

    private function subscriptionChanged(array $subscription): void
    {
        $agreement = MaintenanceAgreement::where('stripe_subscription_id', $subscription['id'] ?? '')->first();
        if (! $agreement) return;
        $status = (string) ($subscription['status'] ?? '');
        $agreement->forceFill([
            'stripe_status' => $status,
            'status' => match ($status) { 'canceled', 'incomplete_expired' => 'canceled', 'paused' => 'paused', default => $agreement->status },
            'current_period_end' => isset($subscription['current_period_end']) ? now()->setTimestamp($subscription['current_period_end']) : $agreement->current_period_end,
        ])->save();
    }

    private function notify(string $headline, array $lines): void
    {
        $owner = config('portfolio.admin_email');
        if (! $owner || ! \App\Support\Mailer::canSend()) return;
        \Illuminate\Support\defer(function () use ($owner, $headline, $lines) {
            try {
                Mail::to($owner)->send(new AdminPaymentNotice($headline, $lines));
            } catch (Throwable $e) {
                Log::error('Payment notice mail failed', ['exception_type' => $e::class]);
            }
        });
    }
}
