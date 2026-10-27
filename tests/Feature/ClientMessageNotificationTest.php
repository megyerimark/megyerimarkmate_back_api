<?php

namespace Tests\Feature;

use App\Mail\ClientMessageReceived;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClientMessageNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function quoteWithToken(): array
    {
        $token = bin2hex(random_bytes(32));
        $quote = new QuoteRequest(['customer_name' => 'Ügyfél', 'email' => 'u@example.com', 'website_type' => 'business', 'project_description' => 'Leírás szövege.']);
        $quote->forceFill(['privacy_notice_acknowledged_at' => now(), 'portal_token_hash' => hash('sha256', $token), 'portal_token_expires_at' => now()->addDays(30)])->save();
        return [$quote, $token];
    }

    public function test_admin_is_emailed_once_per_ten_minutes_and_sees_unread_count(): void
    {
        Mail::fake();
        config()->set(['portfolio.mail_enabled' => true, 'mail.default' => 'smtp', 'portfolio.admin_email' => 'owner@example.com']);
        [$quote, $token] = $this->quoteWithToken();

        $this->postJson('/api/client/project/messages', ['token' => $token, 'body' => 'Szia, kérdésem van.'])->assertCreated();
        $this->postJson('/api/client/project/messages', ['token' => $token, 'body' => 'Még egy kérdés.'])->assertCreated();
        Mail::assertSent(ClientMessageReceived::class, 1);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
        $this->getJson('/api/admin/dashboard')->assertJsonPath('data.messages_unread', 2)
            ->assertJsonPath('data.unread_threads.0.quote_request_id', $quote->id);
        $this->getJson('/api/admin/quote-requests')->assertJsonPath('data.0.unread_messages_count', 2);

        // Megnyitás után olvasottá válnak.
        $this->getJson('/api/admin/quote-requests/'.$quote->id.'/collaboration')->assertOk();
        $this->getJson('/api/admin/dashboard')->assertJsonPath('data.messages_unread', 0);
    }

    public function test_admin_messages_are_never_counted_as_unread(): void
    {
        [$quote] = $this->quoteWithToken();
        $quote->messages()->create(['author' => 'admin', 'body' => 'Válasz az ügyfélnek.']);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->getJson('/api/admin/dashboard')->assertJsonPath('data.messages_unread', 0);
    }

    public function test_reloading_the_portal_does_not_use_up_the_message_limit(): void
    {
        [, $token] = $this->quoteWithToken();
        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/client/project', ['token' => $token])->assertOk();
            $this->postJson('/api/client/project/collaboration', ['token' => $token])->assertOk();
        }
        // Korábban a végpontok közös számlálója miatt itt már 429 jött.
        $this->postJson('/api/client/project/messages', ['token' => $token, 'body' => 'Frissítés után is elmegy?'])->assertCreated();
    }
}
