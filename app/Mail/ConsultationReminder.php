<?php

namespace App\Mail;

use App\Models\ConsultationBooking;
use App\Support\ConsultationCalendar;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Emlékeztető az ügyfélnek kb. 24 órával a konzultáció előtt (naptárfájllal). */
class ConsultationReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ConsultationBooking $booking) {}

    public function envelope(): Envelope
    {
        $start = $this->booking->slot->starts_at->copy()->timezone('Europe/Budapest');
        return new Envelope(
            subject: 'Emlékeztető: '.$this->dayWord().' '.$start->format('H:i').'-kor konzultáció – Megyeri Márk Máté',
            replyTo: array_filter([config('portfolio.admin_email')]),
        );
    }

    public function content(): Content
    {
        $start = $this->booking->slot->starts_at->copy()->timezone('Europe/Budapest')->locale('hu');
        return new Content(view: 'emails.consultation-reminder', with: [
            'start' => $start,
            'end' => $start->copy()->addMinutes((int) $this->booking->slot->duration_minutes),
            'googleUrl' => ConsultationCalendar::googleUrl($this->booking),
            'dayWord' => $this->dayWord(),
        ]);
    }

    /** „ma” / „holnap” / dátum – budapesti idő szerint. */
    public function dayWord(): string
    {
        $start = $this->booking->slot->starts_at->copy()->timezone('Europe/Budapest');
        $today = now('Europe/Budapest');
        return match (true) {
            $start->isSameDay($today) => 'ma',
            $start->isSameDay($today->copy()->addDay()) => 'holnap',
            default => $start->format('Y. m. d.'),
        };
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => ConsultationCalendar::ics($this->booking), 'konzultacio-'.$this->booking->id.'.ics')
                ->withMime('text/calendar; charset=UTF-8; method=PUBLISH'),
        ];
    }
}
