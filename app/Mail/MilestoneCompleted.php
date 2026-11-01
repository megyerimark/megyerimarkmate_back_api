<?php

namespace App\Mail;

use App\Models\ProjectMilestone;
use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Haladásjelentés az ügyfélnek: egy mérföldkő elkészült. */
class MilestoneCompleted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quote, public ProjectMilestone $milestone, public int $done, public int $total, public string $portalUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Haladás a projekteden: „'.$this->milestone->title.'” elkészült – Megyeri Márk Máté',
            replyTo: array_filter([config('portfolio.admin_email')]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.milestone-completed');
    }
}
