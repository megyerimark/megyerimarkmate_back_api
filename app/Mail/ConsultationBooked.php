<?php

namespace App\Mail;

use App\Models\ConsultationBooking;
use App\Support\ConsultationCalendar;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConsultationBooked extends Mailable
{
    use Queueable, SerializesModels;

    /** @param bool $changed Az ügyfél a kezelőlinkkel módosította az időpontot. */
    public function __construct(public ConsultationBooking $booking, public bool $forClient, public bool $changed = false) {}

    public function envelope(): Envelope
    {
        // Az ügyfél a válasszal engem ér el; az admin-példányra válaszolva pedig közvetlenül az ügyfelet.
        $replyTo = $this->forClient
            ? array_filter([config('portfolio.admin_email')])
            : [new Address($this->booking->email, $this->booking->customer_name)];
        $when = $this->booking->slot->starts_at->copy()->timezone('Europe/Budapest')->format('Y. m. d. H:i');
        return new Envelope(
            subject: match (true) {
                $this->forClient && $this->changed => 'Módosított időpont: konzultáció '.$when.' – Megyeri Márk Máté',
                $this->forClient => 'Visszaigazolás: konzultáció '.$when.' – Megyeri Márk Máté',
                $this->changed => 'Konzultáció átfoglalva: '.$this->booking->customer_name.' – '.$when,
                default => 'Új konzultációfoglalás: '.$this->booking->customer_name.' – '.$when,
            },
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.consultation-booked', with: [
            'start' => $this->booking->slot->starts_at->copy()->timezone('Europe/Budapest')->locale('hu'),
            'end' => $this->booking->slot->starts_at->copy()->timezone('Europe/Budapest')->addMinutes((int) $this->booking->slot->duration_minutes),
            'googleUrl' => ConsultationCalendar::googleUrl($this->booking),
            // A kezelőlink csak az ügyfél példányában szerepel (az adminpéldány továbbítható).
            'manageUrl' => $this->forClient ? $this->booking->manageUrl() : null,
            'meetingUrl' => \App\Models\AdminSetting::consultation()['meeting_url'],
        ]);
    }

    /** Naptárbejegyzés melléklet (.ics), hogy egy kattintással a naptárba kerüljön. */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => ConsultationCalendar::ics($this->booking), 'konzultacio-'.$this->booking->id.'.ics')
                ->withMime('text/calendar; charset=UTF-8; method=PUBLISH'),
        ];
    }
}
