<?php

namespace App\Http\Controllers;

use App\Mail\CustomerAcceptanceConfirmation;
use App\Mail\ProposalAccepted;
use App\Support\Stripe;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ClientProjectController extends Controller
{
    private function findByToken(string $token): QuoteRequest
    {
        return \App\Support\ClientAccess::quote($token);
    }

    private function publicData(QuoteRequest $quote): array
    {
        return [
            'reference' => $quote->id,
            'customer_name' => $quote->customer_name,
            'website_type' => $quote->website_type,
            'design_style' => $quote->design_style,
            'industry' => $quote->industry,
            'layout_style' => $quote->layout_style,
            'font_style' => $quote->font_style,
            'status' => $quote->status,
            'created_at' => $quote->created_at,
            'updated_at' => $quote->updated_at,
            'quoted_at' => $quote->quoted_at,
            'quoted_price_huf' => $quote->quoted_at ? $quote->quoted_price_huf : null,
            'list_price_huf' => $quote->quoted_at ? $quote->list_price_huf : null,
            'promo_percent' => $quote->promo_percent,
            'promo_title' => $quote->promo_title,
            'proposal_summary' => $quote->quoted_at ? $quote->proposal_summary : null,
            'proposal_schedule' => $quote->quoted_at ? $quote->proposal_schedule : null,
            'proposal_terms' => $quote->quoted_at ? $quote->proposal_terms : null,
            'proposal_valid_until' => $quote->quoted_at ? $quote->proposal_valid_until : null,
            'proposal_accepted_at' => $quote->proposal_accepted_at,
            'access_expires_at' => $quote->portal_token_expires_at,
            'deposit_percent' => $quote->quoted_at ? $quote->deposit_percent : null,
            'deposit_amount_huf' => $quote->quoted_at ? $quote->depositAmount() : null,
            'deposit_paid_at' => $quote->deposit_paid_at,
            'terms_version' => $quote->terms_version,
            'completed_at' => $quote->completed_at,
            'final_amount_huf' => $quote->completed_at ? $quote->remainingAmount() : null,
            'final_paid_at' => $quote->final_paid_at,
            'handed_over_at' => $quote->handed_over_at,
            'handover_note' => $quote->handed_over_at ? $quote->handover_note : null,
            'online_payment' => Stripe::enabled(),
            'maintenance' => ($m = $quote->maintenanceAgreement) && ($m->billing_enabled || $m->stripe_subscription_id) ? [
                'plan_name' => $m->plan_name,
                'monthly_fee_huf' => $m->monthly_fee_huf,
                'included_services' => $m->included_services,
                'subscription_status' => $m->stripe_status,
                'current_period_end' => $m->current_period_end,
                'can_start' => $m->billing_enabled && ! in_array($m->stripe_status, ['active', 'trialing', 'past_due'], true),
                'can_manage' => (bool) $m->stripe_customer_id,
            ] : null,
            'milestones' => in_array($quote->status, ['accepted', 'completed'], true)
                ? $quote->milestones()->get(['id', 'title', 'description', 'status', 'due_date', 'sort_order'])->toArray()
                : [],
        ];
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $quote = $this->findByToken($data['token']);
        return response()->json(['data' => $this->publicData($quote)])
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex');
    }

    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'accept_terms' => ['required', 'accepted'],
            // Fogyasztó (magánszemély) kifejezett kérése a teljesítés megkezdésére a 14 napos elállási határidőn belül.
            'early_start_consent' => ['sometimes', 'boolean'],
        ]);
        $quote = $this->findByToken($data['token']);
        // Lock while deciding to avoid duplicate acceptance from concurrent requests.
        $alreadyAccepted = false;
        $earlyStart = (bool) ($data['early_start_consent'] ?? false);
        $quote = \Illuminate\Support\Facades\DB::transaction(function () use ($quote, &$alreadyAccepted, $earlyStart) {
            $locked = QuoteRequest::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'accepted' && $locked->proposal_accepted_at) {
                $alreadyAccepted = true;
                return $locked;
            }
            if ($locked->status !== 'quoted' || ! $locked->quoted_at || ! $locked->proposal_valid_until || $locked->proposal_valid_until->isBefore(today())) {
                throw ValidationException::withMessages(['proposal' => 'Ez az ajánlat jelenleg nem fogadható el.']);
            }
            $locked->status = 'accepted';
            $locked->proposal_accepted_at = now();
            // Elektronikus szerződéskötés bizonyítéka (Ptk. / Ekertv.): mit, mikor, melyik ÁSZF-fel fogadtak el.
            $locked->terms_version = config('portfolio.terms_version');
            $locked->acceptance_evidence = [
                'proposal_fingerprint' => $locked->proposalFingerprint(),
                'accepted_at' => now()->toIso8601String(),
                'terms_version' => config('portfolio.terms_version'),
                'price_huf' => $locked->quoted_price_huf,
                'deposit_percent' => $locked->deposit_percent,
                'early_start_consent' => $earlyStart,
                'ip' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            ];
            $locked->save();
            return $locked;
        });
        // Ekertv. 6. §: az elektronikus úton tett elfogadást haladéktalanul vissza kell igazolni az ügyfélnek.
        if (! $alreadyAccepted && \App\Support\Mailer::canSend()) {
            $portalUrl = \App\Support\MailText::frontendLink('/ugyfel/projekt', $data['token'], $quote);
            \Illuminate\Support\defer(function () use ($quote, $portalUrl) {
                try {
                    Mail::to($quote->email)->send(new CustomerAcceptanceConfirmation($quote, $portalUrl));
                } catch (\Throwable $exception) {
                    Log::error('Acceptance confirmation failed', ['quote_id' => $quote->id, 'exception_type' => $exception::class]);
                }
            });
        }
        if (! $alreadyAccepted && \App\Support\Mailer::canSend() && config('portfolio.admin_email')) {
            \Illuminate\Support\defer(function () use ($quote) {
                try {
                    Mail::to(config('portfolio.admin_email'))->send(new ProposalAccepted($quote));
                } catch (\Throwable $exception) {
                    Log::error('Proposal acceptance notice failed', ['quote_id' => $quote->id, 'exception_type' => $exception::class]);
                }
            });
        }
        // Kötelező előleg: elfogadás után azonnal a Stripe fizetési oldalára irányítjuk az ügyfelet.
        // payment_status: redirect | failed | transfer_only (nincs Stripe) | none (nincs fizetendő előleg)
        $paymentUrl = null;
        $paymentStatus = 'none';
        if (\App\Support\DepositCheckout::due($quote)) {
            try {
                $paymentUrl = \App\Support\DepositCheckout::create($quote);
                $paymentStatus = 'redirect';
            } catch (\Throwable $exception) {
                $paymentStatus = 'failed';
                Log::error('Deposit checkout after acceptance failed', ['quote_id' => $quote->id, 'error' => $exception->getMessage()]);
            }
        } elseif ($quote->depositAmount() && ! $quote->deposit_paid_at) {
            $paymentStatus = 'transfer_only';
            Log::warning('Deposit due but online payment is not configured (STRIPE_SECRET)', ['quote_id' => $quote->id]);
        } elseif ($quote->status === 'accepted' && ! $quote->depositAmount()) {
            Log::info('Accepted proposal has no deposit set, no payment redirect', ['quote_id' => $quote->id]);
        }
        return response()->json(['message' => 'Az ajánlat elfogadását rögzítettük.', 'data' => $this->publicData($quote), 'payment_url' => $paymentUrl, 'payment_status' => $paymentStatus])
            ->header('Cache-Control', 'no-store, private');
    }
}
