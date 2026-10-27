<?php

namespace Tests\Feature;

use App\Mail\CustomerAcceptanceConfirmation;
use App\Models\FinanceEntry;
use App\Models\MaintenanceAgreement;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentsTest extends TestCase
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
            'status' => 'quoted', 'quoted_at' => now(), 'quoted_price_huf' => 400000, 'deposit_percent' => 30,
            'proposal_summary' => 'Teljes weboldal.', 'proposal_valid_until' => now()->addDays(10)->toDateString(),
        ], $attrs))->save();
        return $q;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();
        return $u;
    }

    private function webhook(array $event, ?string $secret = 'whsec_test')
    {
        $payload = json_encode($event);
        $t = time();
        $sig = hash_hmac('sha256', $t.'.'.$payload, (string) $secret);
        return $this->call('POST', '/api/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t=$t,v1=$sig", 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    public function test_acceptance_stores_evidence_confirms_to_client_and_exposes_deposit(): void
    {
        Mail::fake();
        config()->set(['portfolio.mail_enabled' => true, 'mail.default' => 'smtp']);
        $q = $this->quote();
        $this->postJson('/api/client/project/accept', ['token' => $this->token, 'accept_terms' => true, 'early_start_consent' => true])->assertOk()
            ->assertJsonPath('data.deposit_amount_huf', 120000)->assertJsonPath('data.deposit_percent', 30);
        $q->refresh();
        $this->assertSame($q->proposalFingerprint(), $q->acceptance_evidence['proposal_fingerprint']);
        $this->assertSame(config('portfolio.terms_version'), $q->terms_version);
        $this->assertTrue($q->acceptance_evidence['early_start_consent']);
        Mail::assertSent(CustomerAcceptanceConfirmation::class, fn ($m) => $m->hasTo('u@example.com'));
    }

    public function test_deposit_checkout_uses_huf_minor_units_and_webhook_marks_paid_once(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/cs_1'])]);
        $q = $this->quote(['status' => 'accepted', 'proposal_accepted_at' => now()]);

        $this->postJson('/api/client/project/deposit/checkout', ['token' => $this->token])->assertOk()->assertJsonPath('url', 'https://checkout.stripe.com/c/cs_1');
        Http::assertSent(fn ($r) => $r['mode'] === 'payment' && (int) $r['line_items'][0]['price_data']['unit_amount'] === 12000000
            && $r['line_items'][0]['price_data']['currency'] === 'huf' && $r['metadata']['quote_request_id'] == $q->id);

        $event = ['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_1', 'payment_status' => 'paid', 'amount_total' => 12000000, 'metadata' => ['type' => 'deposit', 'quote_request_id' => (string) $q->id]]]];
        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk(); // ismételt kézbesítés
        $this->assertNotNull($q->fresh()->deposit_paid_at);
        $this->assertSame(1, FinanceEntry::where('type', 'income')->count());
        $this->assertSame(120000, FinanceEntry::first()->amount_huf);

        // Már kifizetett előleghez nem nyitható újabb fizetés.
        $this->postJson('/api/client/project/deposit/checkout', ['token' => $this->token])->assertUnprocessable();
    }

    public function test_forged_or_unsigned_webhook_is_rejected(): void
    {
        $q = $this->quote(['status' => 'accepted', 'proposal_accepted_at' => now()]);
        $event = ['id' => 'evt_x', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_x', 'payment_status' => 'paid', 'amount_total' => 100, 'metadata' => ['type' => 'deposit', 'quote_request_id' => (string) $q->id]]]];
        $this->webhook($event, 'wrong-secret')->assertStatus(400);
        $this->postJson('/api/stripe/webhook', $event)->assertStatus(400);
        $this->assertNull($q->fresh()->deposit_paid_at);
    }

    public function test_maintenance_subscription_lifecycle(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_2', 'url' => 'https://checkout.stripe.com/c/cs_2'])]);
        $q = $this->quote(['status' => 'accepted', 'proposal_accepted_at' => now()]);
        $agreement = MaintenanceAgreement::create(['quote_request_id' => $q->id, 'plan_name' => 'Alap', 'monthly_fee_huf' => 15000, 'status' => 'draft']);

        $this->postJson('/api/client/project/maintenance/checkout', ['token' => $this->token])->assertUnprocessable(); // még nincs engedélyezve
        $agreement->update(['billing_enabled' => true]);
        $this->postJson('/api/client/project', ['token' => $this->token])->assertJsonPath('data.maintenance.can_start', true);
        $this->postJson('/api/client/project/maintenance/checkout', ['token' => $this->token])->assertOk();
        Http::assertSent(fn ($r) => $r['mode'] === 'subscription' && $r['line_items'][0]['price_data']['recurring']['interval'] === 'month'
            && (int) $r['line_items'][0]['price_data']['unit_amount'] === 1500000);

        $this->webhook(['id' => 'evt_2', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_2', 'mode' => 'subscription', 'customer' => 'cus_1', 'subscription' => 'sub_1',
            'metadata' => ['type' => 'maintenance', 'maintenance_agreement_id' => (string) $agreement->id]]]])->assertOk();
        $agreement->refresh();
        $this->assertSame('active', $agreement->status);
        $this->assertSame('sub_1', $agreement->stripe_subscription_id);

        $this->webhook(['id' => 'evt_3', 'type' => 'invoice.paid', 'data' => ['object' => [
            'id' => 'in_1', 'subscription' => 'sub_1', 'amount_paid' => 1500000, 'lines' => ['data' => [['period' => ['end' => now()->addMonth()->timestamp]]]]]]])->assertOk();
        $this->assertSame(15000, FinanceEntry::where('external_ref', 'stripe:in_1')->value('amount_huf'));
        $this->assertNotNull($agreement->fresh()->last_payment_at);

        $this->webhook(['id' => 'evt_4', 'type' => 'invoice.payment_failed', 'data' => ['object' => ['id' => 'in_2', 'subscription' => 'sub_1']]])->assertOk();
        $this->assertSame('past_due', $agreement->fresh()->stripe_status);

        $this->webhook(['id' => 'evt_5', 'type' => 'customer.subscription.deleted', 'data' => ['object' => ['id' => 'sub_1', 'status' => 'canceled']]])->assertOk();
        $this->assertSame('canceled', $agreement->fresh()->status);
    }

    public function test_admin_can_mark_bank_transfer_deposit_and_deposit_is_locked_after_sending(): void
    {
        $q = $this->quote(['status' => 'accepted', 'proposal_accepted_at' => now()]);
        $this->actingAs($this->admin());
        $this->patchJson('/api/admin/quote-requests/'.$q->id, ['deposit_percent' => 10])->assertUnprocessable();
        $this->postJson('/api/admin/quote-requests/'.$q->id.'/deposit-paid')->assertOk();
        $this->assertSame('transfer', $q->fresh()->deposit_paid_via);
        $this->assertSame(120000, FinanceEntry::sole()->amount_huf);
        $this->postJson('/api/admin/quote-requests/'.$q->id.'/deposit-paid')->assertUnprocessable();
    }

    public function test_payment_endpoints_are_unavailable_without_stripe_keys(): void
    {
        config()->set('services.stripe.secret', null);
        $this->quote(['status' => 'accepted', 'proposal_accepted_at' => now()]);
        $this->postJson('/api/client/project/deposit/checkout', ['token' => $this->token])->assertUnprocessable();
        $this->postJson('/api/client/project', ['token' => $this->token])->assertJsonPath('data.online_payment', false);
    }

    public function test_accepting_returns_stripe_payment_url_for_mandatory_deposit(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_9', 'url' => 'https://checkout.stripe.com/c/cs_9'])]);
        $this->quote();
        $this->postJson('/api/client/project/accept', ['token' => $this->token, 'accept_terms' => true])
            ->assertOk()->assertJsonPath('payment_url', 'https://checkout.stripe.com/c/cs_9');
        Http::assertSent(fn ($r) => (int) $r['line_items'][0]['price_data']['unit_amount'] === 12000000);
    }

    public function test_acceptance_still_succeeds_when_stripe_is_down(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'down']], 500)]);
        $this->quote();
        $this->postJson('/api/client/project/accept', ['token' => $this->token, 'accept_terms' => true])
            ->assertOk()->assertJsonPath('data.status', 'accepted')->assertJsonPath('payment_url', null);
    }

    public function test_no_payment_url_without_stripe(): void
    {
        config()->set('services.stripe.secret', null);
        $this->quote();
        $this->postJson('/api/client/project/accept', ['token' => $this->token, 'accept_terms' => true])
            ->assertOk()->assertJsonPath('payment_url', null)->assertJsonPath('data.deposit_amount_huf', 120000);
    }
}
