<?php

namespace Tests\Feature;

use App\Mail\FollowUpReminder;
use App\Models\QuoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FollowUpRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config()->set(['portfolio.mail_enabled' => true, 'mail.default' => 'smtp', 'portfolio.admin_email' => 'owner@example.com']);
    }

    private function quote(string $name, array $attributes): QuoteRequest
    {
        $q = new QuoteRequest(['customer_name' => $name, 'email' => strtolower($name).'@example.com', 'website_type' => 'business', 'project_description' => 'Céges weboldal készítése']);
        $q->forceFill(['privacy_notice_acknowledged_at' => now(), ...$attributes])->save();
        return $q;
    }

    public function test_admin_gets_one_digest_about_unanswered_requests_and_silent_proposals(): void
    {
        $old = $this->quote('Regi', ['status' => 'new', 'created_at' => now()->subDays(3)]);
        $this->quote('Friss', ['status' => 'new', 'created_at' => now()->subHours(10)]);
        $this->quote('Folyamatban', ['status' => 'reviewing', 'created_at' => now()->subDays(3)]);
        $silent = $this->quote('Csendes', ['status' => 'quoted', 'quoted_at' => now()->subDays(6), 'quoted_price_huf' => 300000, 'proposal_valid_until' => now()->addDays(8)]);
        $this->quote('Lejart', ['status' => 'quoted', 'quoted_at' => now()->subDays(20), 'proposal_valid_until' => now()->subDay()]);
        $this->quote('Elfogadta', ['status' => 'accepted', 'quoted_at' => now()->subDays(6), 'proposal_accepted_at' => now()->subDays(2)]);

        $this->artisan('portfolio:follow-up-reminders')->assertSuccessful();
        Mail::assertSent(FollowUpReminder::class, 1);
        Mail::assertSent(FollowUpReminder::class, function (FollowUpReminder $mail) use ($old, $silent) {
            $this->assertSame([$old->id], $mail->unanswered->modelKeys());
            $this->assertSame([$silent->id], $mail->silent->modelKeys());
            $mail->assertSeeInHtml('Csendes');
            $mail->assertSeeInHtml('300 000 Ft');
            $mail->assertDontSeeInHtml('Friss');
            return $mail->hasTo('owner@example.com');
        });
        $this->assertNotNull($old->fresh()->reply_reminded_at);
        $this->assertNotNull($silent->fresh()->quote_reminded_at);

        // tételenként csak egyszer
        $this->artisan('portfolio:follow-up-reminders')->assertSuccessful();
        Mail::assertSent(FollowUpReminder::class, 1);
    }

    public function test_nothing_is_sent_without_admin_email(): void
    {
        config()->set('portfolio.admin_email', '');
        $this->quote('Regi', ['status' => 'new', 'created_at' => now()->subDays(3)]);
        $this->artisan('portfolio:follow-up-reminders')->assertSuccessful();
        Mail::assertNothingSent();
    }
}
