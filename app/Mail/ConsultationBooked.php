<?php

namespace App\Mail;

use App\Models\ConsultationBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConsultationBooked extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ConsultationBooking $booking, public bool $forClient) {}
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Konzultációs időpont #'.$this->booking->id.' – Megyeri Márk Máté');
    }
    public function content(): Content { return new Content(view: 'emails.consultation-booked'); }
}
