<?php

namespace App\Mail;

use App\Models\ProjectMessage;
use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientMessageReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quote, public ProjectMessage $projectMessage, public string $adminUrl)
    {
        // A levél nyelve: az ügyfél nyelve (az admin példányok mindig magyarul).
        $this->locale('hu');
    }

    public function envelope(): Envelope
    {
        // Válasz az adminpéldányra: közvetlenül az ügyfélnek megy.
        return new Envelope(subject: 'Új üzenet: '.$this->quote->customer_name.' (#'.$this->quote->id.')', replyTo: [new \Illuminate\Mail\Mailables\Address($this->quote->email, $this->quote->customer_name)]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.client-message');
    }
}
