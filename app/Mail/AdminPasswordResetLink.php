<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminPasswordResetLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $adminName, public string $resetUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Admin jelszó-visszaállítás – Megyeri Márk');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-password-reset');
    }
}
