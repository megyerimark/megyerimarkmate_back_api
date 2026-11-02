<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/** Admin-összesítő: megválaszolatlan megkeresések és válasz nélküli ajánlatok (portfolio:follow-up-reminders). */
class FollowUpReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Collection $unanswered, public Collection $silent)
    {
        $this->locale('hu');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Utánkövetés: '.($this->unanswered->count() + $this->silent->count()).' ügyfél vár rád – Megyeri Márk');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.follow-up-reminder', with: [
            'adminUrl' => rtrim((string) config('portfolio.frontend_url'), '/').'/admin?ajanlat=',
        ]);
    }
}
