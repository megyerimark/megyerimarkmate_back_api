<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerQuoteConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quote, public string $portalUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ajánlatkérésed beérkezett – Megyeri Márk | Webfejlesztés');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.customer-confirmation');
    }
}
