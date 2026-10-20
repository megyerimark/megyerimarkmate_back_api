<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Mail\CustomerQuoteConfirmation;
use App\Mail\NewQuoteNotification;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QuoteRequestController extends Controller
{
    public function store(StoreQuoteRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['website', 'privacy_notice_acknowledged']);
        $quote = new QuoteRequest($data);
        $quote->privacy_notice_acknowledged_at = now();
        $quote->save();

        if (config('portfolio.mail_enabled')) {
            try {
                if ($owner = config('portfolio.admin_email')) {
                    Mail::to($owner)->send(new NewQuoteNotification($quote));
                }
                if (config('portfolio.customer_receipt_enabled')) {
                    Mail::to($quote->email)->send(new CustomerQuoteConfirmation($quote));
                }
            } catch (\Throwable $exception) {
                // The enquiry is already saved: SMTP failure must not create a duplicate submission.
                Log::error('Quote notification delivery failed', [
                    'quote_id' => $quote->id,
                    'exception_type' => $exception::class,
                ]);
            }
        }

        return response()->json([
            'message' => 'Az ajánlatkérés sikeresen beérkezett.',
            'reference' => $quote->id,
        ], 201);
    }
}
