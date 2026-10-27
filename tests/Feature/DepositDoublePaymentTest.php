<?php

namespace Tests\Feature;

use App\Mail\AdminPaymentNotice;
use App\Models\FinanceEntry;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Dupla előlegfizetés elleni védelem: munkamenet-újrahasznosítás, idempotencia, dupla befizetés jelzése. */
class DepositDoublePaymentTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test']);
    }

    private function quote(array $attrs = []): QuoteRequest
    {
        $this->token = bin2hex(random_bytes(32));
        $q = new QuoteRequest(['customer_name' => 'Ügyfél Kft', 'email' => 'u@example.com', 'website_type' => 'business', 'project_description' => 'Leírás szövege.']);
        $q->forceFill(array_merge([
            'privacy_notice_acknowledged_at' => now(), 'portal_token_hash' => hash('sha256', $this->token), 'portal_token_expires_at' => now()->addDays(30),
            'status' => 'accepted', 'quoted_at' => now(), 'proposal_accepted_at' => now(), 'quoted_price_huf' => 400000, 'deposit_percent' => 30,
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

    private function paidEvent(string $eventId, string $sessionId, QuoteRequest $q): array
    {
        return ['id' => $eventId, 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => $sessionId, 'payment_status' => 'paid', 'amount_total' => 12000000,
            'metadata' => ['type' => 'deposit', 'quote_request_id' => (string) $q->id]]]];
    }

    public function test_repeated_clicks_reuse_the_same_open_checkout_session(): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/cs_1' => Http::response(['id' => 'cs_1', 'status' => 'open', 'payment_status' => 'unpaid', 'amount_total' => 12000000]),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/cs_1', 'expires_at' => now()->addDay()->timestamp]),
        ]);
        $q = $this->quote();

        $this->postJson('/api/client/project/deposit/checkout', ['token' => $this->token])->assertOk()->assertJsonPath('url', 'https://checkout.stripe.com/c/cs_1');
        $this->postJson('/api/client/project/deposit/checkout', ['token' => $this->token])->assertOk()->assertJsonPath('url', 'https://checkout.stripe.com/c/cs_1');

        $created = collect(Http::recorded())->filter(fn ($pair) => $pair[0]->method() === 'POST' && str_ends_with($pair[0]->url(), '/checkout/sessions'));
        $this->assertCount(1, $created, 'Csak egy fizetési munkamenet nyílhat.');
        $this->assertTrue($created->first()[0]->hasHeader('Idempotency-Key'));
        $this->assertSame('cs_1', $q->fresh()->deposit_checkout_session_id);
    }

    public function test_completed_but_unconfirmed_session_blocks_a_second_payment(): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/cs_1' => Http::response(['id' => 'cs_1', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 12000000]),
        ]);
        $this->quote(['deposit_checkout_session_id' => 'cs_1', 'deposit_checkout_url' => 'https://checkout.stripe.com/c/cs_1', 'deposit_checkout_expires_at' => now()->addHours(10)]);

        $this->postJson('/api/client/project/deposit/checkout', ['token' => $this->token])
            ->assertUnprocessable()->assertJsonPath('errors.payment.0', fn ($m) => str_contains($m, 'már megtörtént'));
        Http::assertNotSent(fn (HttpRequest $r) => $r->method() === 'POST');
    }

    public function test_expired_session_or_changed_amount_opens_a_new_one(): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/cs_old' => Http::response(['id' => 'cs_old', 'status' => 'open', 'amount_total' => 9900000]),
            'api.stripe.com/v1/checkout/sessions/cs_old/expire' => Http::response(['id' => 'cs_old', 'status' => 'expired']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_new', 'url' => 'https://checkout.stripe.com/c/cs_new']),
        ]);
        $q = $this->quote(['deposit_checkout_session_id' => 'cs_old', 'deposit_checkout_url' => 'https://checkout.stripe.com/c/cs_old', 'deposit_checkout_expires_at' => now()->addHours(10)]);

        $this->postJson('/api/client/project/deposit/checkout', ['token' => $this->token])->assertOk()->assertJsonPath('url', 'https://checkout.stripe.com/c/cs_new');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/checkout/sessions/cs_old/expire'));
        $this->assertSame('cs_new', $q->fresh()->deposit_checkout_session_id);
    }

    public function test_second_paid_session_is_recorded_and_flagged_for_refund(): void
    {
        Mail::fake();
        config()->set(['portfolio.mail_enabled' => true, 'mail.default' => 'smtp', 'portfolio.admin_email' => 'admin@example.com']);
        $q = $this->quote();

        $this->webhook($this->paidEvent('evt_a', 'cs_a', $q))->assertOk();
        $this->webhook($this->paidEvent('evt_b', 'cs_b', $q))->assertOk();

        $this->assertSame('stripe', $q->fresh()->deposit_paid_via);
        $this->assertSame(2, FinanceEntry::count(), 'Mindkét befizetés látszik a Pénzügyekben.');
        $this->assertStringContainsString('visszatérítendő', FinanceEntry::where('external_ref', 'stripe:cs_b')->value('category'));
        Mail::assertSent(AdminPaymentNotice::class, 2);
    }

    public function test_manual_transfer_closes_the_open_card_checkout(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_1/expire' => Http::response(['id' => 'cs_1', 'status' => 'expired'])]);
        $q = $this->quote(['deposit_checkout_session_id' => 'cs_1', 'deposit_checkout_url' => 'https://checkout.stripe.com/c/cs_1', 'deposit_checkout_expires_at' => now()->addHours(10)]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->postJson('/api/admin/quote-requests/'.$q->id.'/deposit-paid')->assertOk();
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/checkout/sessions/cs_1/expire'));
        $this->assertNull($q->fresh()->deposit_checkout_session_id);
    }

    public function test_accept_reports_failed_checkout_and_system_status_shows_stripe_error(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'Not a valid URL', 'param' => 'success_url']], 400)]);
        $this->quote(['status' => 'quoted', 'proposal_accepted_at' => null]);

        $this->postJson('/api/client/project/accept', ['token' => $this->token, 'accept_terms' => true])
            ->assertOk()->assertJsonPath('payment_url', null)->assertJsonPath('payment_status', 'failed')
            ->assertJsonPath('data.online_payment', true);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $stripe = collect($this->actingAs($admin)->getJson('/api/admin/system-status')->assertOk()->json('data.checks'))->firstWhere('key', 'stripe');
        $this->assertFalse($stripe['ok']);
        $this->assertStringContainsString('Not a valid URL', $stripe['hint']);
        $this->assertStringContainsString('success_url', $stripe['hint']);
    }

    public function test_accept_without_stripe_reports_transfer_only(): void
    {
        config()->set('services.stripe.secret', null);
        $this->quote(['status' => 'quoted', 'proposal_accepted_at' => null]);
        $this->postJson('/api/client/project/accept', ['token' => $this->token, 'accept_terms' => true])
            ->assertOk()->assertJsonPath('payment_status', 'transfer_only')->assertJsonPath('data.online_payment', false);
    }

    public function test_accept_returns_redirect_status_with_url(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_ok', 'url' => 'https://checkout.stripe.com/c/cs_ok'])]);
        $this->quote(['status' => 'quoted', 'proposal_accepted_at' => null]);
        $this->postJson('/api/client/project/accept', ['token' => $this->token, 'accept_terms' => true])
            ->assertOk()->assertJsonPath('payment_status', 'redirect')->assertJsonPath('payment_url', 'https://checkout.stripe.com/c/cs_ok');
    }
}
