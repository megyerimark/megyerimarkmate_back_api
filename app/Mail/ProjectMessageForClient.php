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

    public function __construct(public QuoteRequest $quote, public ProjectMessage $projectMessage, public string $portalUrl)
    {
        // A levél nyelve: az ügyfél nyelve (az admin példányok mindig magyarul).
        $this->locale(\App\Support\MailText::langOf($quote));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: mt('Új üzenet a projekteddel kapcsolatban (#:id) – Megyeri Márk Máté', ['id' => $this->quote->id]),
            replyTo: array_filter([config('portfolio.admin_email')]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.project-message-client');
    }
}
