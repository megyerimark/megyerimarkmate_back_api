<?php

namespace App\Mail;

use App\Models\ProjectMessage;
use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Értesítés az ügyfélnek: üzenetet írtam neki a projektoldalon (a válasz ott írható meg). */
class ProjectMessageForClient extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quote, public ProjectMessage $projectMessage, public string $portalUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Új üzenet a projekteddel kapcsolatban (#'.$this->quote->id.') – Megyeri Márk Máté',
            replyTo: array_filter([config('portfolio.admin_email')]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.project-message-client');
    }
}
