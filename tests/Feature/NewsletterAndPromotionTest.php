<?php

namespace Tests\Feature;

use App\Mail\NewsletterConfirmation;
use App\Models\NewsletterSubscriber;
use App\Models\QuoteRequest;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewsletterAndPromotionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();
        return $user;
    }

    private function enableMail(): void
    {
        Mail::fake();
        config()->set('portfolio.mail_enabled', true);
        config()->set('mail.default', 'smtp');
    }

    public function test_double_opt_in_confirm_and_unsubscribe(): void
    {
        $this->enableMail();
        $this->postJson('/api/newsletter/subscribe', ['email' => 'Anna@Example.com'])->assertStatus(422); // hozzájárulás nélkül nem
        $this->postJson('/api/newsletter/subscribe', ['email' => 'Anna@Example.com', 'consent' => true, 'lang' => 'hu'])->assertOk();

        $sub = NewsletterSubscriber::firstOrFail();
        $this->assertSame('anna@example.com', $sub->email);
        $this->assertSame('pending', $sub->status);
        $this->assertNotEmpty($sub->consent_text);

        $link = null;
        Mail::assertSent(NewsletterConfirmation::class, function (NewsletterConfirmation $mail) use (&$link) { $link = $mail->confirmUrl; return true; });
        $token = substr($link, strpos($link, '#') + 1);

        $this->postJson('/api/newsletter/confirm', ['token' => str_repeat('a', 64)])->assertNotFound();
        $res = $this->postJson('/api/newsletter/confirm', ['token' => $token])->assertOk();
        $this->assertSame('confirmed', $sub->fresh()->status);
        $this->postJson('/api/newsletter/confirm', ['token' => $token])->assertNotFound(); // egyszer használható

        $this->postJson('/api/newsletter/unsubscribe', ['token' => $res->json('unsubscribe_token')])->assertOk();
        $this->assertSame('unsubscribed', $sub->fresh()->status);
    }

    public function test_subscribe_does_not_reveal_existing_and_limits_resends(): void
    {
        $this->enableMail();
        $first = $this->postJson('/api/newsletter/subscribe', ['email' => 'b@example.com', 'consent' => true])->assertOk()->json('message');
        $second = $this->postJson('/api/newsletter/subscribe', ['email' => 'b@example.com', 'consent' => true])->assertOk()->json('message');
        $this->assertSame($first, $second);
        Mail::assertSent(NewsletterConfirmation::class, 1); // 10 percen belül nincs újabb levél
        $this->postJson('/api/newsletter/subscribe', ['email' => 'bot@example.com', 'consent' => true, 'website' => 'spam'])->assertStatus(422);
    }

    public function test_admin_list_export_and_delete(): void
    {
        $this->enableMail();
        $this->postJson('/api/newsletter/subscribe', ['email' => 'c@example.com', 'consent' => true]);
        $sub = NewsletterSubscriber::first();
        $sub->forceFill(['status' => 'confirmed', 'confirmed_at' => now()])->save();

        $this->getJson('/api/admin/newsletter')->assertUnauthorized();
        config()->set('portfolio.require_admin_2fa', false);
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/admin/newsletter')->assertOk()->assertJsonPath('counts.confirmed', 1)
            ->assertJsonMissingPath('data.0.unsubscribe_token');
        $csv = $this->actingAs($admin)->get('/api/admin/exports/newsletter')->assertOk()->streamedContent();
        $this->assertStringContainsString('c@example.com', $csv);
        $this->actingAs($admin)->deleteJson('/api/admin/newsletter/'.$sub->id)->assertOk();
        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_promotion_is_public_expires_and_is_recorded_on_quote(): void
    {
        config()->set('portfolio.mail_enabled', false);
        config()->set('portfolio.require_admin_2fa', false);
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson('/api/admin/site-settings', ['promo_enabled' => true, 'promo_percent' => 0])->assertStatus(422);
        $this->actingAs($admin)->patchJson('/api/admin/site-settings', [
            'promo_enabled' => true, 'promo_percent' => 15, 'promo_title' => 'Őszi akció', 'promo_ends_at' => now()->addDays(5)->format('Y-m-d'),
        ])->assertOk();

        $this->getJson('/api/site-settings')->assertOk()->assertJsonPath('data.promo.active', true)->assertJsonPath('data.promo.percent', 15);

        $this->postJson('/api/quote-requests', [
            'customer_name' => 'Teszt Ügyfél', 'email' => 'teszt@example.com', 'website_type' => 'business',
            'project_description' => 'Céges weboldalt szeretnék ajánlatkérővel.', 'privacy_notice_acknowledged' => true,
            'promo_percent' => 90, // a látogató nem írhatja felül
        ])->assertCreated();
        $this->assertSame(15, QuoteRequest::first()->promo_percent);

        // Lejárt akció → nem aktív, és a gyorsítótár azonnal frissül az admin módosításra.
        $this->actingAs($admin)->patchJson('/api/admin/site-settings', ['promo_ends_at' => now()->subDay()->format('Y-m-d')])->assertOk();
        $this->getJson('/api/site-settings')->assertJsonPath('data.promo.active', false);
    }

    public function test_public_content_cache_is_invalidated_on_admin_change(): void
    {
        $this->getJson('/api/site-settings')->assertJsonPath('data.contact_phone', '');
        SiteSetting::updateOrCreate(['key' => 'contact_phone'], ['value' => '+36301234567']);
        $this->getJson('/api/site-settings')->assertJsonPath('data.contact_phone', '+36301234567')
            ->assertHeader('Cache-Control', 'max-age=60, private, stale-while-revalidate=600');
    }
}
