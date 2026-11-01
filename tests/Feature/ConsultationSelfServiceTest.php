<?php

namespace Tests\Feature;

use App\Mail\ConsultationBooked;
use App\Mail\ConsultationCancelled;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ConsultationSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config()->set(['portfolio.mail_enabled' => true, 'mail.default' => 'smtp', 'portfolio.admin_email' => 'owner@example.com']);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();
        return $u;
    }

    /** Foglal egy időpontot, és visszaadja a levélben kapott személyes kulcsot. */
    private function bookAndGetToken(ConsultationSlot $slot): string
    {
        $this->postJson('/api/consultations/book', [
            'consultation_slot_id' => $slot->id, 'customer_name' => 'Kiss Anna', 'email' => 'anna@example.com', 'privacy_notice_acknowledged' => true,
        ])->assertCreated();
        $mail = Mail::sent(ConsultationBooked::class, fn ($m) => $m->forClient)->first();
        $this->assertNotNull($mail);
        $this->assertSame(1, preg_match('/foglalasom#([a-f0-9]{64})/', $mail->render(), $m));
        // az adminpéldányban nincs kezelőlink
        $adminMail = Mail::sent(ConsultationBooked::class, fn ($x) => ! $x->forClient)->first();
        $this->assertStringNotContainsString('foglalasom#', $adminMail->render());
        return $m[1];
    }

    public function test_client_can_view_and_reschedule_with_personal_link(): void
    {
        $a = ConsultationSlot::create(['starts_at' => now()->addDays(3), 'duration_minutes' => 30]);
        $b = ConsultationSlot::create(['starts_at' => now()->addDays(4), 'duration_minutes' => 45]);
        $token = $this->bookAndGetToken($a);

        $this->postJson('/api/consultations/manage', ['token' => $token])->assertOk()
            ->assertJsonPath('data.changeable', true)->assertJsonPath('data.available.0.id', $b->id)
            ->assertJsonMissingPath('data.email');
        $this->postJson('/api/consultations/manage/reschedule', ['token' => $token, 'consultation_slot_id' => $b->id])->assertOk();
        $this->assertSame($b->id, ConsultationBooking::first()->consultation_slot_id);
        $this->assertFalse($a->fresh()->booking()->exists());
        Mail::assertSent(ConsultationBooked::class, fn ($m) => $m->changed && $m->forClient && $m->hasTo('anna@example.com'));
        Mail::assertSent(ConsultationBooked::class, fn ($m) => $m->changed && ! $m->forClient && $m->hasTo('owner@example.com'));

        // a foglalt időpontra nem lehet átfoglalni
        $c = ConsultationSlot::create(['starts_at' => now()->addDays(5), 'duration_minutes' => 30]);
        ConsultationBooking::create(['consultation_slot_id' => $c->id, 'customer_name' => 'Más', 'email' => 'mas@example.com', 'privacy_notice_acknowledged_at' => now()]);
        $this->postJson('/api/consultations/manage/reschedule', ['token' => $token, 'consultation_slot_id' => $c->id])->assertUnprocessable();
    }

    public function test_client_can_cancel_and_slot_becomes_free(): void
    {
        $slot = ConsultationSlot::create(['starts_at' => now()->addDays(2), 'duration_minutes' => 30]);
        $token = $this->bookAndGetToken($slot);
        $this->postJson('/api/consultations/manage/cancel', ['token' => $token])->assertOk();
        $this->assertSame(0, ConsultationBooking::count());
        Mail::assertSent(ConsultationCancelled::class, 2);
        $this->getJson('/api/consultations/available')->assertOk()->assertJsonPath('data.0.id', $slot->id);
        $this->postJson('/api/consultations/manage', ['token' => $token])->assertNotFound();
    }

    public function test_changes_are_locked_two_hours_before_and_bad_tokens_fail(): void
    {
        $slot = ConsultationSlot::create(['starts_at' => now()->addDays(2), 'duration_minutes' => 30]);
        $token = $this->bookAndGetToken($slot);
        $slot->forceFill(['starts_at' => now()->addHour()])->save();
        $this->postJson('/api/consultations/manage', ['token' => $token])->assertOk()->assertJsonPath('data.changeable', false);
        $this->postJson('/api/consultations/manage/cancel', ['token' => $token])->assertUnprocessable();
        $this->postJson('/api/consultations/manage', ['token' => str_repeat('a', 64)])->assertNotFound();
        $this->postJson('/api/consultations/manage', ['token' => 'rossz'])->assertUnprocessable();
    }

    public function test_meeting_link_is_private_setting_and_appears_in_mail(): void
    {
        $this->actingAs($this->admin())->putJson('/api/admin/consultation-settings', ['meeting_url' => 'http://nem-biztonsagos.hu'])->assertUnprocessable();
        $this->putJson('/api/admin/consultation-settings', ['meeting_url' => 'https://meet.google.com/abc-defg-hij'])->assertOk();
        $this->getJson('/api/site-settings')->assertOk()->assertDontSee('meet.google.com');

        $slot = ConsultationSlot::create(['starts_at' => now()->addDays(3), 'duration_minutes' => 30]);
        $this->bookAndGetToken($slot);
        Mail::assertSent(ConsultationBooked::class, function (ConsultationBooked $m) {
            if (! $m->forClient) return false;
            $m->assertSeeInHtml('https://meet.google.com/abc-defg-hij');
            return true;
        });
        // a kezelőkulcs az admin listában sem jelenik meg
        $this->actingAs($this->admin())->getJson('/api/admin/consultations')->assertOk()->assertDontSee('manage_token');
    }
}
