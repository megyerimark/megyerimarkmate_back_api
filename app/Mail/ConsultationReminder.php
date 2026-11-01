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

    public function __construct(public ConsultationBooking $booking)
    {
        // A levél nyelve: az ügyfél nyelve (az admin példányok mindig magyarul).
        $this->locale(\App\Support\MailText::langOf($booking));
    }

    public function envelope(): Envelope
    {
        $start = $this->booking->slot->starts_at->copy()->timezone('Europe/Budapest');
        return new Envelope(
            subject: mt('Emlékeztető: :day :time-kor konzultáció – Megyeri Márk Máté', ['day' => $this->dayWord(), 'time' => $start->format('H:i')]),
            replyTo: array_filter([config('portfolio.admin_email')]),
        );
    }

    public function content(): Content
    {
        $start = $this->booking->slot->starts_at->copy()->timezone('Europe/Budapest')->locale(\App\Support\MailText::isEnglish() ? 'en' : 'hu');
        return new Content(view: 'emails.consultation-reminder', with: [
            'start' => $start,
            'end' => $start->copy()->addMinutes((int) $this->booking->slot->duration_minutes),
            'googleUrl' => ConsultationCalendar::googleUrl($this->booking),
            'dayWord' => $this->dayWord(),
            'manageUrl' => $this->booking->manageUrl(),
            'meetingUrl' => \App\Models\AdminSetting::consultation()['meeting_url'],
        ]);
    }

    /** „ma” / „holnap” / dátum – budapesti idő szerint. */
    public function dayWord(): string
    {
        $start = $this->booking->slot->starts_at->copy()->timezone('Europe/Budapest');
        $today = now('Europe/Budapest');
        return match (true) {
            $start->isSameDay($today) => mt('ma'),
            $start->isSameDay($today->copy()->addDay()) => mt('holnap'),
            default => \App\Support\MailText::date($start),
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
