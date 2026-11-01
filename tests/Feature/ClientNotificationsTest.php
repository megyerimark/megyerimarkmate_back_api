<?php

namespace Tests\Feature;

use App\Mail\MilestoneCompleted;
use App\Mail\ProjectMessageForClient;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClientNotificationsTest extends TestCase
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

    private function quote(bool $accepted = true): QuoteRequest
    {
        $q = new QuoteRequest(['customer_name' => 'Kiss Anna', 'email' => 'anna@example.com', 'website_type' => 'business', 'project_description' => 'Céges weboldal készítése']);
        $q->forceFill(['privacy_notice_acknowledged_at' => now(), 'proposal_accepted_at' => $accepted ? now() : null])->save();
        return $q;
    }

    public function test_client_is_emailed_about_admin_messages_at_most_every_ten_minutes(): void
    {
        $q = $this->quote();
        $this->actingAs($this->admin());
        $this->postJson("/api/admin/quote-requests/{$q->id}/messages", ['body' => 'Elkészült a dizájnterv, nézd meg kérlek.'])->assertCreated();
        $this->postJson("/api/admin/quote-requests/{$q->id}/messages", ['body' => 'Még egy kiegészítés.'])->assertCreated();
        Mail::assertSent(ProjectMessageForClient::class, 1);
        Mail::assertSent(ProjectMessageForClient::class, function (ProjectMessageForClient $mail) {
            $mail->assertHasReplyTo('owner@example.com');
            $mail->assertSeeInHtml('Elkészült a dizájnterv');
            $this->assertStringContainsString('/ugyfel/projekt#', $mail->portalUrl);
            return $mail->hasTo('anna@example.com');
        });
        // a link működik is
        $token = parse_url(Mail::sent(ProjectMessageForClient::class)->first()->portalUrl, PHP_URL_FRAGMENT);
        $this->postJson('/api/client/project', ['token' => $token])->assertOk();
    }

    public function test_milestone_completion_sends_progress_once(): void
    {
        $q = $this->quote();
        $this->actingAs($this->admin());
        $first = $this->postJson("/api/admin/quote-requests/{$q->id}/milestones", ['title' => 'Dizájnterv'])->assertCreated()->json('data.id');
        $this->postJson("/api/admin/quote-requests/{$q->id}/milestones", ['title' => 'Fejlesztés'])->assertCreated();

        $this->patchJson("/api/admin/quote-requests/{$q->id}/milestones/{$first}", ['status' => 'in_progress'])->assertOk();
        Mail::assertNotSent(MilestoneCompleted::class);
        $this->patchJson("/api/admin/quote-requests/{$q->id}/milestones/{$first}", ['status' => 'done'])->assertOk();
        $this->patchJson("/api/admin/quote-requests/{$q->id}/milestones/{$first}", ['status' => 'done', 'title' => 'Dizájnterv kész'])->assertOk();
        Mail::assertSent(MilestoneCompleted::class, 1);
        Mail::assertSent(MilestoneCompleted::class, fn (MilestoneCompleted $m) => $m->done === 1 && $m->total === 2 && $m->hasTo('anna@example.com'));
    }

    public function test_no_milestone_mail_before_acceptance_or_when_disabled(): void
    {
        $this->actingAs($this->admin());
        $q = $this->quote(false);
        $id = $this->postJson("/api/admin/quote-requests/{$q->id}/milestones", ['title' => 'Egyeztetés'])->json('data.id');
        $this->patchJson("/api/admin/quote-requests/{$q->id}/milestones/{$id}", ['status' => 'done'])->assertOk();

        $accepted = $this->quote();
        $id2 = $this->postJson("/api/admin/quote-requests/{$accepted->id}/milestones", ['title' => 'Belső lépés'])->json('data.id');
        $this->patchJson("/api/admin/quote-requests/{$accepted->id}/milestones/{$id2}", ['status' => 'done', 'notify_client' => false])->assertOk();
        Mail::assertNotSent(MilestoneCompleted::class);
    }
}
