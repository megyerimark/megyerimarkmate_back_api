<?php

namespace Tests\Feature;

use App\Mail\ProjectCompleted;
use App\Mail\ProjectHandedOver;
use App\Models\FinanceEntry;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Projektlezárás: végleges e-mail, fennmaradó összeg, és a kódátadás zárolása a kifizetésig. */
class ProjectCompletionTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        config()->set([
            'portfolio.mail_enabled' => true, 'mail.default' => 'smtp', 'portfolio.admin_email' => 'owner@example.com',
            'portfolio.require_admin_2fa' => false,
            'services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test',
        ]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();
        return $u;
    }

    /** Elfogadott, 400 000 Ft-os projekt 30% (120 000 Ft) befizetett előleggel → 280 000 Ft marad. */
    private function quote(array $attrs = []): QuoteRequest
    {
        $this->token = bin2hex(random_bytes(32));
        $q = new QuoteRequest(['customer_name' => 'Ügyfél Kft', 'email' => 'u@example.com', 'website_type' => 'business', 'project_description' => 'Leírás szövege.']);
        $q->forceFill(array_merge([
            'privacy_notice_acknowledged_at' => now(), 'portal_token_hash' => hash('sha256', $this->token),
            'portal_token_encrypted' => Crypt::encryptString($this->token), 'portal_token_expires_at' => now()->addDays(30),
            'status' => 'accepted', 'quoted_at' => now(), 'proposal_accepted_at' => now(), 'quoted_price_huf' => 400000, 'deposit_percent' => 30,
            'deposit_paid_at' => now(), 'deposit_paid_via' => 'transfer',
            'proposal_summary' => 'Teljes weboldal.', 'proposal_valid_until' => now()->addDays(10)->toDateString(),
        ], $attrs))->save();
        return $q;
    }

    private function webhook(array $event)
    {
        $payload = json_encode($event);
        $t = time();
        $sig = hash_hmac('sha256', $t.'.'.$payload, 'whsec_test');
        return $this->call('POST', '/api/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t=$t,v1=$sig", 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    private function uploadDeliverable(User $admin, QuoteRequest $q): int
    {
        return $this->actingAs($admin)->post("/api/admin/quote-requests/{$q->id}/documents", [
            'title' => 'Forráskód', 'deliverable' => '1', 'file' => UploadedFile::fake()->create('weboldal.zip', 500, 'application/zip'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    }

    public function test_status_cannot_be_set_to_completed_without_the_completion_flow(): void
    {
        $q = $this->quote();
        $this->actingAs($this->admin())->patchJson("/api/admin/quote-requests/{$q->id}", ['status' => 'completed'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('accepted', $q->fresh()->status);
    }

    public function test_completion_sends_the_final_email_and_records_the_remaining_amount(): void
    {
        $q = $this->quote();
        $this->actingAs($this->admin())->postJson("/api/admin/quote-requests/{$q->id}/complete")
            ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.final_amount_huf', 280000);

        Mail::assertSent(ProjectCompleted::class, function (ProjectCompleted $mail) {
            $html = $mail->render();
            $this->assertStringContainsString('280 000 Ft', $html);
            $this->assertStringContainsString('/ugyfel/projekt#'.$this->token, $html);
            return $mail->hasTo('u@example.com');
        });
        $this->postJson('/api/client/project', ['token' => $this->token])
            ->assertOk()->assertJsonPath('data.final_amount_huf', 280000)->assertJsonPath('data.handed_over_at', null);
    }

    public function test_completion_needs_the_deposit_and_a_working_mailer(): void
    {
        $unpaid = $this->quote(['deposit_paid_at' => null, 'deposit_paid_via' => null]);
        $this->actingAs($this->admin())->postJson("/api/admin/quote-requests/{$unpaid->id}/complete")
            ->assertUnprocessable()->assertJsonValidationErrors('deposit');

        config()->set('mail.default', 'log');
        $q = $this->quote();
        $this->actingAs($this->admin())->postJson("/api/admin/quote-requests/{$q->id}/complete")->assertStatus(503);
        $this->assertSame('accepted', $q->fresh()->status);
        $this->assertNull($q->fresh()->completed_at);
        Mail::assertNothingSent();
    }

    public function test_code_handover_is_locked_until_the_remaining_amount_is_paid(): void
    {
        $admin = $this->admin();
        $q = $this->quote();
        $docId = $this->uploadDeliverable($admin, $q);
        $this->actingAs($admin)->postJson("/api/admin/quote-requests/{$q->id}/complete")->assertOk();

        // Fizetés előtt: az admin nem adhatja át, az ügyfél nem töltheti le.
        $this->actingAs($admin)->postJson("/api/admin/quote-requests/{$q->id}/handover")
            ->assertUnprocessable()->assertJsonPath('errors.handover.0', fn ($m) => str_contains($m, '280 000 Ft'));
        $this->postJson('/api/client/project/collaboration', ['token' => $this->token])
            ->assertOk()->assertJsonPath('data.documents.0.locked', true);
        $this->postJson("/api/client/project/documents/{$docId}/download", ['token' => $this->token])->assertForbidden();

        // Átutalás beérkezett → átadható.
        $this->actingAs($admin)->postJson("/api/admin/quote-requests/{$q->id}/final-paid")->assertOk();
        $this->assertSame(280000, (int) FinanceEntry::where('quote_request_id', $q->id)->where('category', 'Végszámla')->value('amount_huf'));
        $this->actingAs($admin)->postJson("/api/admin/quote-requests/{$q->id}/handover", ['note' => 'GitHub-repó meghívó elküldve.'])->assertOk();
        Mail::assertSent(ProjectHandedOver::class, fn (ProjectHandedOver $m) => $m->hasTo('u@example.com') && str_contains($m->render(), 'GitHub-repó'));

        $this->postJson('/api/client/project/collaboration', ['token' => $this->token])->assertJsonPath('data.documents.0.locked', false);
        $this->postJson("/api/client/project/documents/{$docId}/download", ['token' => $this->token])->assertOk();
        // Kétszer nem adható át.
        $this->actingAs($admin)->postJson("/api/admin/quote-requests/{$q->id}/handover")->assertUnprocessable();
    }

    public function test_nothing_left_to_pay_unlocks_the_handover_right_after_completion(): void
    {
        $admin = $this->admin();
        $q = $this->quote(['deposit_percent' => 100]);
        $this->uploadDeliverable($admin, $q);
        $this->actingAs($admin)->postJson("/api/admin/quote-requests/{$q->id}/complete")->assertOk()->assertJsonPath('data.final_amount_huf', 0);
        $this->actingAs($admin)->postJson("/api/admin/quote-requests/{$q->id}/final-paid")->assertUnprocessable();
        $this->actingAs($admin)->postJson("/api/admin/quote-requests/{$q->id}/handover")->assertOk();
    }

    public function test_final_card_payment_opens_checkout_and_the_webhook_unlocks_the_handover(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_final', 'url' => 'https://checkout.stripe.com/c/cs_final', 'expires_at' => now()->addDay()->timestamp])]);
        $admin = $this->admin();
        $q = $this->quote();
        $this->postJson('/api/client/project/final/checkout', ['token' => $this->token])->assertUnprocessable(); // még nincs lezárva
        $this->actingAs($admin)->postJson("/api/admin/quote-requests/{$q->id}/complete")->assertOk();

        $this->postJson('/api/client/project/final/checkout', ['token' => $this->token])
            ->assertOk()->assertJsonPath('url', 'https://checkout.stripe.com/c/cs_final');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/checkout/sessions')
            && $r['line_items'][0]['price_data']['unit_amount'] == 28000000 && $r['metadata']['type'] === 'final');

        $this->webhook(['id' => 'evt_final', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_final', 'payment_status' => 'paid', 'amount_total' => 28000000,
            'metadata' => ['type' => 'final', 'quote_request_id' => (string) $q->id]]]])->assertOk();

        $fresh = $q->fresh();
        $this->assertNotNull($fresh->final_paid_at);
        $this->assertSame('stripe', $fresh->final_paid_via);
        $this->assertNull($fresh->final_checkout_session_id);
        $this->assertNotNull($fresh->deposit_paid_at, 'A végösszeg-fizetés nem írhatja felül az előleg adatait.');
        $this->assertTrue(FinanceEntry::where('external_ref', 'stripe:cs_final')->where('category', 'Végszámla')->exists());
        $this->actingAs($admin)->postJson("/api/admin/quote-requests/{$q->id}/handover", ['note' => 'Tárhely-hozzáférés átadva.'])->assertOk();
    }
}
