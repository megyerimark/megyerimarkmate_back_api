<?php

namespace Tests\Feature;

use App\Mail\CustomerQuoteConfirmation;
use App\Mail\ProposalReady;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Az akció az ügyfél számára is látható és ellenőrizhető: visszaigazolás, ajánlat (eredeti + akciós ár), projektoldal. */
class PromotionTransparencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config()->set(['portfolio.mail_enabled' => true, 'mail.default' => 'smtp', 'portfolio.require_admin_2fa' => false, 'portfolio.min_deposit_percent' => 30]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();
        return $u;
    }

    private function promoQuote(): QuoteRequest
    {
        $this->actingAs($this->admin())->patchJson('/api/admin/site-settings', [
            'promo_enabled' => true, 'promo_percent' => 20, 'promo_title' => 'Őszi akció', 'promo_ends_at' => now()->addDays(5)->format('Y-m-d'),
        ])->assertOk();
        $this->postJson('/api/quote-requests', [
            'customer_name' => 'Teszt Ügyfél', 'email' => 'teszt@example.com', 'website_type' => 'business',
            'project_description' => 'Céges weboldalt szeretnék ajánlatkérővel.', 'privacy_notice_acknowledged' => true,
        ])->assertCreated()->assertJsonPath('promo_percent', 20)->assertJsonPath('promo_title', 'Őszi akció');
        return QuoteRequest::firstOrFail();
    }

    public function test_receipt_email_confirms_the_recorded_discount(): void
    {
        $this->promoQuote();
        Mail::assertSent(CustomerQuoteConfirmation::class, fn (CustomerQuoteConfirmation $m) => str_contains($m->render(), '−20%'));
    }

    public function test_promo_proposal_needs_a_list_price_and_honours_the_discount(): void
    {
        $admin = $this->admin();
        $q = $this->promoQuote();
        $base = ['proposal_summary' => 'Céges weboldal öt aloldallal.', 'proposal_valid_until' => now()->addDays(10)->toDateString(), 'deposit_percent' => 30];

        // Akció előtti ár nélkül nem küldhető ki.
        $this->actingAs($admin)->patchJson("/api/admin/quote-requests/{$q->id}", $base + ['quoted_price_huf' => 400000])->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/quote-requests/{$q->id}/send-proposal")
            ->assertUnprocessable()->assertJsonValidationErrors('list_price_huf');

        // A −20% miatt 500 000 Ft akció előtti árnál legfeljebb 400 000 Ft lehet.
        $this->actingAs($admin)->patchJson("/api/admin/quote-requests/{$q->id}", ['list_price_huf' => 450000])
            ->assertUnprocessable()->assertJsonValidationErrors('quoted_price_huf');
        $this->actingAs($admin)->patchJson("/api/admin/quote-requests/{$q->id}", ['list_price_huf' => 500000])->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/quote-requests/{$q->id}/send-proposal")->assertOk();

        Mail::assertSent(ProposalReady::class, function (ProposalReady $m) {
            $html = $m->render();
            return str_contains($html, '500 000 Ft') && str_contains($html, '−20%') && str_contains($html, '400 000 Ft');
        });
        $this->assertNotEmpty($q->fresh()->proposalFingerprint());
    }

    public function test_list_price_is_rejected_without_a_promotion(): void
    {
        $q = new QuoteRequest(['customer_name' => 'Kiss Anna', 'email' => 'anna@example.com', 'website_type' => 'business', 'project_description' => 'Üzleti weboldal fejlesztése']);
        $q->forceFill(['privacy_notice_acknowledged_at' => now()])->save();
        $this->actingAs($this->admin())->patchJson("/api/admin/quote-requests/{$q->id}", ['quoted_price_huf' => 300000, 'list_price_huf' => 350000])
            ->assertUnprocessable()->assertJsonValidationErrors('list_price_huf');
    }
}
