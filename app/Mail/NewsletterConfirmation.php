<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public NewsletterSubscriber $subscriber, public string $confirmUrl)
    {
        // A levél nyelve: az ügyfél nyelve (az admin példányok mindig magyarul).
        $this->locale(\App\Support\MailText::langOf($subscriber));
    }

    public function envelope(): Envelope
    {
        // Az ügyfél válasza a saját postafiókomba érkezik (akkor is, ha a feladó noreply-cím).
        return new Envelope(subject: $this->subscriber->lang === 'en'
            ? 'Please confirm your subscription – Megyeri Márk Máté'
            : 'Erősítsd meg a feliratkozásod – Megyeri Márk Máté', replyTo: array_filter([config('portfolio.admin_email')]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.newsletter-confirmation');
    }
}
