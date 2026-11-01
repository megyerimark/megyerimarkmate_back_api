<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/** Egy hírlevél egy címzettnek – saját leiratkozó linkkel (a levelezők „Leiratkozás” gombja is erre mutat). */
class NewsletterCampaignMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $mailSubject,
        public string $body,
        public ?string $buttonText,
        public ?string $buttonUrl,
        public string $unsubscribeUrl,
        public bool $isTest = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->isTest ? '[TESZT] ' : '').$this->mailSubject,
            replyTo: array_filter([config('portfolio.admin_email')]),
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>']);
    }

    public function content(): Content
    {
        // Egyszerű, biztonságos formázás: üres sor = új bekezdés, „- ” kezdetű sorok = felsorolás. HTML nem engedélyezett.
        $blocks = [];
        foreach (preg_split('/\R{2,}/', trim($this->body)) as $chunk) {
            $lines = preg_split('/\R/', trim($chunk));
            if ($lines && collect($lines)->every(fn ($l) => str_starts_with(ltrim($l), '- '))) {
                $blocks[] = ['type' => 'list', 'items' => array_map(fn ($l) => trim(substr(ltrim($l), 2)), $lines)];
            } else {
                $blocks[] = ['type' => 'p', 'text' => trim($chunk)];
            }
        }
        return new Content(view: 'emails.newsletter-campaign', with: ['blocks' => $blocks]);
    }
}
