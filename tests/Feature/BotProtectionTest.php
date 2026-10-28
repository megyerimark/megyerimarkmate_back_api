<?php

namespace Tests\Feature;

use App\Models\ConsultationSlot;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotProtectionTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private function quotePayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Teszt Elek', 'email' => 'teszt@example.com', 'website_type' => 'business',
            'project_description' => 'Egy új céges weboldalra lenne szükségem.', 'privacy_notice_acknowledged' => true,
        ], $overrides);
    }

    private function enableTurnstile(mixed $response = ['success' => true, 'hostname' => 'megyerimarkmate.hu']): void
    {
        config()->set([
            'services.turnstile.site_key' => 'site-key',
            'services.turnstile.secret_key' => 'secret-key',
            'services.turnstile.hostnames' => 'megyerimarkmate.hu,www.megyerimarkmate.hu',
        ]);
        Http::fake([self::VERIFY_URL => is_array($response) ? Http::response($response) : $response]);
    }

    public function test_forms_work_without_turnstile_keys(): void
    {
        Http::fake();
        $this->postJson('/api/quote-requests', $this->quotePayload())->assertCreated();
        Http::assertNothingSent();
        $this->getJson('/api/site-settings')->assertOk()->assertJsonPath('data.turnstile_site_key', null);
    }

    public function test_site_key_is_published_when_enabled(): void
    {
        $this->enableTurnstile();
        $this->getJson('/api/site-settings')->assertOk()->assertJsonPath('data.turnstile_site_key', 'site-key');
    }

    public function test_missing_token_is_rejected(): void
    {
        $this->enableTurnstile();
        $this->postJson('/api/quote-requests', $this->quotePayload())
            ->assertStatus(422)->assertJsonPath('error_code', 'TURNSTILE_FAILED');
        $this->assertSame(0, QuoteRequest::count());
        Http::assertNothingSent();
    }

    public function test_valid_token_is_verified_with_cloudflare(): void
    {
        $this->enableTurnstile();
        $this->postJson('/api/quote-requests', $this->quotePayload(['turnstile_token' => 'valid-token']))->assertCreated();
        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::VERIFY_URL
            && $request['secret'] === 'secret-key' && $request['response'] === 'valid-token');
    }

    public function test_failed_token_is_rejected(): void
    {
        $this->enableTurnstile(['success' => false, 'error-codes' => ['invalid-input-response']]);
        $this->postJson('/api/newsletter/subscribe', ['email' => 'a@example.com', 'consent' => true, 'turnstile_token' => 'bad'])
            ->assertStatus(422)->assertJsonPath('error_code', 'TURNSTILE_FAILED');
    }

    public function test_token_issued_for_another_domain_is_rejected(): void
    {
        // Más domainen kiállított (pl. ellopott) kulcs sem fogadható el.
        $this->enableTurnstile(['success' => true, 'hostname' => 'evil.example']);
        $this->postJson('/api/quote-requests', $this->quotePayload(['turnstile_token' => 'stolen']))->assertStatus(422);
        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_unreachable_cloudflare_fails_closed(): void
    {
        $this->enableTurnstile(Http::failedConnection());
        $this->postJson('/api/quote-requests', $this->quotePayload(['turnstile_token' => 'x']))->assertStatus(422);
        $this->assertSame(0, QuoteRequest::count());
    }

    public function test_admin_login_and_password_reset_require_token(): void
    {
        $this->withHeader('Origin', config('app.url'));
        $this->enableTurnstile();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->postJson('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertStatus(422);
        $this->postJson('/admin/forgot-password', ['email' => $admin->email])->assertStatus(422);
        $this->postJson('/admin/login', ['email' => $admin->email, 'password' => 'password', 'turnstile_token' => 'ok'])
            ->assertSuccessful();
    }

    public function test_one_email_cannot_book_the_whole_calendar(): void
    {
        $slots = collect(range(1, 3))->map(fn ($day) => ConsultationSlot::create([
            'starts_at' => now()->addDays($day), 'duration_minutes' => 30,
        ]));
        $book = fn ($slot, $email) => $this->postJson('/api/consultations/book', [
            'consultation_slot_id' => $slot->id, 'customer_name' => 'Teszt', 'email' => $email,
            'privacy_notice_acknowledged' => true,
        ]);

        $book($slots[0], 'bot@example.com')->assertCreated();
        $book($slots[1], 'BOT@example.com')->assertCreated();
        $book($slots[2], 'bot@Example.com')->assertStatus(422)->assertJsonValidationErrors('email');
        $book($slots[2], 'masik@example.com')->assertCreated();
    }
}
