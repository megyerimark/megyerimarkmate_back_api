<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Mail\CustomerQuoteConfirmation;
use App\Mail\NewQuoteNotification;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

class QuoteRequestController extends Controller
{
    public function store(StoreQuoteRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['website', 'privacy_notice_acknowledged']);
        $quote = new QuoteRequest($data);
        $quote->privacy_notice_acknowledged_at = now();
        // The public receipt exposes only a numeric reference; the access secret travels by email.
        $portalToken = bin2hex(random_bytes(32));
        $quote->portal_token_hash = hash('sha256', $portalToken);
        $quote->portal_token_encrypted = Crypt::encryptString($portalToken);
        $quote->portal_token_expires_at = now()->addDays(config('portfolio.client_access_days'));
        $quote->save();

        // Never put portal access secrets in the log / array mail transports.
        if (config('portfolio.mail_enabled') && ! in_array(config('mail.default'), ['log', 'array', 'null'], true)) {
            if ($owner = config('portfolio.admin_email')) {
                try {
                    Mail::to($owner)->send(new NewQuoteNotification($quote));
                } catch (\Throwable $exception) {
                    Log::error('Owner quote notification failed', ['quote_id' => $quote->id, 'exception_type' => $exception::class]);
                }
            }
            if (config('portfolio.customer_receipt_enabled')) {
                try {
                    Mail::to($quote->email)->send(new CustomerQuoteConfirmation($quote, config('portfolio.frontend_url').'/ugyfel/projekt#'.$portalToken));
                } catch (\Throwable $exception) {
                    // A request already stored must not be submitted again because SMTP failed.
                    Log::error('Customer quote receipt failed', ['quote_id' => $quote->id, 'exception_type' => $exception::class]);
                }
            }
        }

        return response()->json([
            'message' => 'Az ajánlatkérés sikeresen beérkezett.',
            'reference' => $quote->id,
        ], 201);
    }
}
