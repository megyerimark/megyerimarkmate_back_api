<?php

namespace Tests\Feature;

use App\Mail\ConsultationBooked;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Support\ConsultationCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ConsultationMailTest extends TestCase
{
    use RefreshDatabase;

    private function book(string $email = 'anna@example.com'): void
    {
        $slot = ConsultationSlot::create(['starts_at' => Carbon::parse('2030-03-12 09:30:00', 'UTC'), 'duration_minutes' => 30]);
        $this->postJson('/api/consultations/book', [
            'consultation_slot_id' => $slot->id, 'customer_name' => 'Kiss Anna', 'email' => $email,
            'phone' => '+36 30 123 4567', 'notes' => 'Webshop', 'privacy_notice_acknowledged' => true,
        ])->assertCreated();
    }

    public function test_client_and_admin_get_their_own_version_with_calendar_attachment(): void
    {
        Mail::fake();
        config()->set('portfolio.mail_enabled', true);
        config()->set('mail.default', 'smtp');
        config()->set('portfolio.admin_email', 'owner@example.com');
        $this->book();

        Mail::assertSent(ConsultationBooked::class, 2);
        Mail::assertSent(ConsultationBooked::class, function (ConsultationBooked $mail) {
            if (! $mail->hasTo('anna@example.com')) return false;
            $this->assertTrue($mail->forClient);
            $mail->assertHasReplyTo('owner@example.com');
            // budapesti idő (UTC 09:30 → 10:30), nem UTC
            $mail->assertSeeInHtml('10:30 – 11:00');
            $mail->assertSeeInHtml('Google Naptár');
            $mail->assertDontSeeInHtml('+36 30 123 4567');
            $this->assertCount(1, $mail->attachments());
            return true;
        });
        Mail::assertSent(ConsultationBooked::class, function (ConsultationBooked $mail) {
            if (! $mail->hasTo('owner@example.com')) return false;
            $this->assertFalse($mail->forClient);
            $mail->assertHasReplyTo('anna@example.com');
            $mail->assertSeeInHtml('+36 30 123 4567');
            return true;
        });
    }

    public function test_only_one_mail_when_client_uses_the_admin_address(): void
    {
        Mail::fake();
        config()->set('portfolio.mail_enabled', true);
        config()->set('mail.default', 'smtp');
        config()->set('portfolio.admin_email', 'Owner@Example.com');
        $this->book('owner@example.com');
        Mail::assertSent(ConsultationBooked::class, 1);
    }

    public function test_ics_is_valid_and_in_utc(): void
    {
        $slot = ConsultationSlot::create(['starts_at' => Carbon::parse('2030-03-12 09:30:00', 'UTC'), 'duration_minutes' => 45]);
        $booking = ConsultationBooking::create(['consultation_slot_id' => $slot->id, 'customer_name' => 'Teszt', 'email' => 't@example.com', 'privacy_notice_acknowledged_at' => now()]);
        $ics = ConsultationCalendar::ics($booking->load('slot'));
        $this->assertStringContainsString("DTSTART:20300312T093000Z\r\n", $ics);
        $this->assertStringContainsString("DTEND:20300312T101500Z\r\n", $ics);
        foreach (explode("\r\n", $ics) as $line) $this->assertLessThanOrEqual(75, strlen($line));
        $this->assertStringContainsString('dates=20300312T093000Z%2F20300312T101500Z', ConsultationCalendar::googleUrl($booking));
    }
}
