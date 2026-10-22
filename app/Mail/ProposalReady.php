<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProposalReady extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quote, public string $portalUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Elkészült az egyedi ajánlatod (#'.$this->quote->id.') – Megyeri Márk Máté');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.proposal-ready');
    }
}
