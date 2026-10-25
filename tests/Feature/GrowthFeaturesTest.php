<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\SiteEvent;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        return $admin;
    }

    private function quote(array $extra = []): QuoteRequest
    {
        return QuoteRequest::create(array_merge([
            'customer_name' => 'Kiss Anna',
            'email' => 'anna@example.com',
            'website_type' => 'business',
            'project_description' => 'Üzleti weboldal fejlesztése',
        ], $extra));
    }

    public function test_only_published_testimonials_are_public(): void
    {
        Testimonial::create(['author_name' => 'Nagy Béla', 'quote' => str_repeat('Kiváló munka. ', 3), 'is_published' => true]);
        Testimonial::create(['author_name' => 'Rejtett', 'quote' => str_repeat('Nem látszik. ', 3), 'is_published' => false]);

        $this->getJson('/api/testimonials')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_testimonial_admin_is_protected_and_validates(): void
    {
        $this->getJson('/api/admin/testimonials')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/admin/testimonials')->assertForbidden();

        $this->actingAs($this->admin());
        $this->postJson('/api/admin/testimonials', ['author_name' => 'X', 'quote' => 'rövid'])->assertUnprocessable();
        $this->postJson('/api/admin/testimonials', [
            'author_name' => 'Nagy Béla',
            'quote' => 'Gyors, korrekt és nagyon igényes munka volt.',
            'rating' => 5,
            'is_published' => true,
        ])->assertCreated();
        $this->assertDatabaseCount('testimonials', 1);
    }

    public function test_events_endpoint_counts_only_known_events_and_skips_bots(): void
    {
        $this->postJson('/api/events', ['event' => 'page_view', 'path' => '/'])->assertNoContent();
        $this->postJson('/api/events', ['event' => 'hack', 'path' => '/'])->assertUnprocessable();
        $this->postJson('/api/events', ['event' => 'page_view', 'path' => '/'], ['User-Agent' => 'Googlebot/2.1'])->assertNoContent();

        $this->assertSame(1, SiteEvent::count());
    }

    public function test_quote_keeps_attribution(): void
    {
        $this->postJson('/api/quote-requests', [
            'customer_name' => 'Kiss Anna',
            'email' => 'anna@example.com',
            'website_type' => 'business',
            'project_description' => 'Üzleti weboldal fejlesztése',
            'privacy_notice_acknowledged' => true,
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'referrer_host' => 'google.com',
            'landing_path' => '/',
        ])->assertCreated();

        $this->assertDatabaseHas('quote_requests', ['utm_source' => 'google', 'referrer_host' => 'google.com']);
    }

    public function test_growth_endpoint_is_admin_only_and_reports_channels(): void
    {
        $this->getJson('/api/admin/growth')->assertUnauthorized();

        $this->quote(['utm_source' => 'google']);
        $this->actingAs($this->admin())->getJson('/api/admin/growth')
            ->assertOk()
            ->assertJsonPath('data.channels.0.channel', 'google')
            ->assertJsonStructure(['data' => ['funnel', 'channels', 'response', 'attention', 'revenue', 'trust']]);
    }

    public function test_contacted_and_follow_up(): void
    {
        $quote = $this->quote();
        $this->actingAs($this->admin());

        $this->postJson('/api/admin/quote-requests/'.$quote->id.'/contacted')->assertOk();
        $this->assertNotNull($quote->fresh()->first_response_at);

        $date = now()->addDays(3)->format('Y-m-d');
        $this->patchJson('/api/admin/quote-requests/'.$quote->id, ['follow_up_at' => $date])->assertOk();
        $this->assertSame($date, $quote->fresh()->follow_up_at->format('Y-m-d'));
    }

    public function test_csv_export_neutralises_formula_injection(): void
    {
        $this->quote(['customer_name' => '=HYPERLINK("http://evil")']);
        $response = $this->actingAs($this->admin())->get('/api/admin/exports/quote-requests');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
    }
}
