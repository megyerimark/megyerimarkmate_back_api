<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Rövid admin-értesítő fizetési eseményről (előleg beérkezett, előfizetés indult, sikertelen terhelés). */
class AdminPaymentNotice extends Mailable
{
    use Queueable, SerializesModels;

    /** @param string[] $lines */
    public function __construct(public string $headline, public array $lines) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-payment-notice');
    }
}
