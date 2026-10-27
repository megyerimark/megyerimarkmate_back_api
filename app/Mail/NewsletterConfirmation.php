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

    public function __construct(public NewsletterSubscriber $subscriber, public string $confirmUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subscriber->lang === 'en'
            ? 'Please confirm your subscription – Megyeri Márk Máté'
            : 'Erősítsd meg a feliratkozásod – Megyeri Márk Máté');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.newsletter-confirmation');
    }
}
