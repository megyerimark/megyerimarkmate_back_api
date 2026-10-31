<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Néhány nappal a kódátadás után: rövid köszönet és kérés egy Google-értékelésre. */
class ReviewRequest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quote, public string $reviewUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Hogy vagy elégedett az új weboldaladdal? – Megyeri Márk Máté',
            replyTo: array_filter([config('portfolio.admin_email')]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.review-request');
    }
}
