<?php

namespace App\Mail;

use App\Models\ConsultationBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Konzultáció lemondva (az ügyfél a kezelőlinkkel mondta le): visszaigazolás neki, értesítés nekem. */
class ConsultationCancelled extends Mailable
{
    use Queueable;

    public function __construct(public ConsultationBooking $booking, public bool $forClient)
    {
        // A levél nyelve: az ügyfél nyelve (az admin példányok mindig magyarul).
        $this->locale($forClient ? \App\Support\MailText::langOf($booking) : 'hu');
    }

    public function envelope(): Envelope
    {
        $when = $this->booking->slot->starts_at->copy()->timezone('Europe/Budapest')->format('Y. m. d. H:i');
        return new Envelope(
            subject: $this->forClient ? mt('Lemondtad a konzultációt (:when) – Megyeri Márk Máté', ['when' => \App\Support\MailText::dateTime($this->booking->slot->starts_at)]) : 'Konzultáció lemondva: '.$this->booking->customer_name.' – '.$when,
            replyTo: $this->forClient ? array_filter([config('portfolio.admin_email')]) : [new Address($this->booking->email, $this->booking->customer_name)],
        );
    }

    public function content(): Content
    {
        $start = $this->booking->slot->starts_at->copy()->timezone('Europe/Budapest')->locale(\App\Support\MailText::isEnglish() ? 'en' : 'hu');
        return new Content(view: 'emails.consultation-cancelled', with: [
            'start' => $start,
            'end' => $start->copy()->addMinutes((int) $this->booking->slot->duration_minutes),
            'bookUrl' => rtrim((string) config('portfolio.frontend_url'), '/').(\App\Support\MailText::isEnglish() ? '/en/consultation' : '/konzultacio'),
        ]);
    }
}
