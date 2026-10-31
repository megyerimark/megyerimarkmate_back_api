<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerAcceptanceConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quote, public string $portalUrl) {}

    public function envelope(): Envelope
    {
        // Az ügyfél válasza a saját postafiókomba érkezik (akkor is, ha a feladó noreply-cím).
        return new Envelope(subject: 'Visszaigazolás: elfogadtad a #'.$this->quote->id.' számú ajánlatot', replyTo: array_filter([config('portfolio.admin_email')]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.customer-acceptance');
    }
}
