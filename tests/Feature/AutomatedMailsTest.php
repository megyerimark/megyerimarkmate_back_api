<?php

namespace Tests\Feature;

use App\Mail\ConsultationReminder;
use App\Mail\ReviewRequest;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Models\QuoteRequest;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AutomatedMailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config()->set(['portfolio.mail_enabled' => true, 'mail.default' => 'smtp', 'portfolio.admin_email' => 'owner@example.com']);
    }

    private function booking(int $hoursAhead, int $bookedHoursAgo): ConsultationBooking
    {
        $slot = ConsultationSlot::create(['starts_at' => now()->addHours($hoursAhead), 'duration_minutes' => 30]);
        $booking = ConsultationBooking::create(['consultation_slot_id' => $slot->id, 'customer_name' => 'Kiss Anna',
            'email' => 'anna'.$hoursAhead.'@example.com', 'privacy_notice_acknowledged_at' => now()]);
        $booking->forceFill(['created_at' => now()->subHours($bookedHoursAgo)])->save();
        return $booking;
    }

    public function test_consultation_reminder_is_sent_once_about_a_day_before(): void
    {
        $due = $this->booking(20, 72);
        $this->booking(60, 72);          // még messze van
        $this->booking(1, 72);           // túl közel (2 órán belül)
        $this->booking(21, 1);           // most foglalta – épp megkapta a visszaigazolást (más időpont: a starts_at egyedi)

        $this->artisan('portfolio:consultation-reminders')->assertSuccessful();
        Mail::assertSent(ConsultationReminder::class, 1);
        Mail::assertSent(ConsultationReminder::class, function (ConsultationReminder $mail) use ($due) {
            $mail->assertHasReplyTo('owner@example.com');
            $mail->assertSeeInHtml($due->slot->starts_at->copy()->timezone('Europe/Budapest')->format('H:i'));
            $this->assertCount(1, $mail->attachments());
            return $mail->hasTo($due->email);
        });
        $this->assertNotNull($due->fresh()->reminder_sent_at);

        $this->artisan('portfolio:consultation-reminders')->assertSuccessful();
        Mail::assertSent(ConsultationReminder::class, 1);
    }

    private function handedOver(string $email, int $daysAgo): QuoteRequest
    {
        $q = new QuoteRequest(['customer_name' => 'Nagy Béla', 'email' => $email, 'website_type' => 'business', 'project_description' => 'Céges weboldal készítése']);
        $q->forceFill(['privacy_notice_acknowledged_at' => now(), 'handed_over_at' => now()->subDays($daysAgo)])->save();
        return $q;
    }

    public function test_review_request_needs_a_google_link_and_goes_out_once(): void
    {
        $due = $this->handedOver('due@example.com', 6);
        $this->handedOver('fresh@example.com', 2);
        $this->handedOver('old@example.com', 60);

        $this->artisan('portfolio:review-requests')->assertSuccessful();
        Mail::assertNothingSent();

        SiteSetting::updateOrCreate(['key' => 'google_review_url'], ['value' => 'https://g.page/r/teszt/review']);
        $this->artisan('portfolio:review-requests')->assertSuccessful();
        Mail::assertSent(ReviewRequest::class, 1);
        Mail::assertSent(ReviewRequest::class, function (ReviewRequest $mail) {
            $mail->assertSeeInHtml('https://g.page/r/teszt/review');
            return $mail->hasTo('due@example.com');
        });
        $this->assertNotNull($due->fresh()->review_requested_at);

        $this->artisan('portfolio:review-requests')->assertSuccessful();
        Mail::assertSent(ReviewRequest::class, 1);
    }
}
