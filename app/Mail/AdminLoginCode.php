<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminLoginCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $adminName, public string $code)
    {
        // A levél nyelve: az ügyfél nyelve (az admin példányok mindig magyarul).
        $this->locale('hu');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bejelentkezési ellenőrzőkód – Megyeri Márk');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-login-code');
    }
}
