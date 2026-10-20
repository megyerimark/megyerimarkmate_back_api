<?php

namespace Tests\Feature;

use App\Mail\CustomerQuoteConfirmation;
use App\Mail\NewQuoteNotification;
use App\Models\PortfolioProject;
use App\Models\QuoteRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortfolioBackendTest extends TestCase
{
    use RefreshDatabase;

    private function sampleQuote(): array
    {
        return [
            'customer_name' => 'Teszt Ügyfél',
            'email' => 'teszt@example.com',
            'website_type' => 'business',
            'design_style' => 'dark-premium',
            'colors' => ['#7950F2'],
            'features' => ['kapcsolatfelvétel'],
            'project_description' => 'Céges weboldalt szeretnék ajánlatkérővel.',
            'privacy_notice_acknowledged' => true,
        ];
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();
        return $user;
    }

    public function test_an_enquiry_is_saved_and_cannot_set_admin_fields(): void
    {
        config()->set('portfolio.mail_enabled', false);
        $response = $this->postJson('/api/quote-requests', array_merge($this->sampleQuote(), [
            'status' => 'accepted', 'admin_notes' => 'injected',
        ]));
        $response->assertCreated()->assertJsonPath('reference', 1);
        $this->assertDatabaseHas('quote_requests', [
            'email' => 'teszt@example.com', 'status' => 'new', 'admin_notes' => null,
        ]);
        $this->assertNotNull(QuoteRequest::first()->privacy_notice_acknowledged_at);
    }

    public function test_invalid_data_and_spam_are_rejected(): void
    {
        $this->postJson('/api/quote-requests', [
            'customer_name' => 'Bad', 'website_type' => 'invalid',
        ])->assertUnprocessable();
        $this->postJson('/api/quote-requests', array_merge($this->sampleQuote(), [
            'website' => 'spam-link',
        ]))->assertUnprocessable();
        $this->assertDatabaseCount('quote_requests', 0);
    }

    public function test_owner_and_customer_mails_are_sent_when_enabled(): void
    {
        Mail::fake();
        config()->set('portfolio.mail_enabled', true);
        config()->set('portfolio.admin_email', 'owner@example.com');
        config()->set('portfolio.customer_receipt_enabled', true);
        $this->postJson('/api/quote-requests', $this->sampleQuote())->assertCreated();
        Mail::assertSent(NewQuoteNotification::class, 1);
        Mail::assertSent(CustomerQuoteConfirmation::class, 1);
    }

    public function test_admin_routes_are_protected(): void
    {
        $this->getJson('/api/admin/quote-requests')->assertUnauthorized();
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/quote-requests')->assertForbidden();
        $this->actingAs($this->admin())
            ->getJson('/api/admin/quote-requests')->assertOk();
    }

    public function test_only_an_admin_can_log_in(): void
    {
        $guest = User::factory()->create();
        $this->postJson('/admin/login', [
            'email' => $guest->email, 'password' => 'password',
        ])->assertUnauthorized();

        $admin = $this->admin();
        $this->postJson('/admin/login', [
            'email' => $admin->email, 'password' => 'wrong-password',
        ])->assertUnauthorized();
        $this->postJson('/admin/login', [
            'email' => $admin->email, 'password' => 'password',
        ])->assertOk();
        $this->getJson('/api/admin/me')->assertOk();
        $this->postJson('/admin/logout')->assertOk();
        $this->getJson('/api/admin/me')->assertUnauthorized();
    }

    public function test_admin_can_update_and_delete_quote(): void
    {
        $admin = $this->admin();
        $quote = QuoteRequest::create($this->sampleQuoteForModel());
        $this->actingAs($admin)->patchJson('/api/admin/quote-requests/'.$quote->id, [
            'status' => 'quoted', 'quoted_price_huf' => 199000, 'admin_notes' => 'Felhívni',
        ])->assertOk()->assertJsonPath('data.status', 'quoted');
        $this->assertDatabaseHas('quote_requests', ['id' => $quote->id, 'quoted_price_huf' => 199000]);
        $this->actingAs($admin)->deleteJson('/api/admin/quote-requests/'.$quote->id)->assertOk();
        $this->assertDatabaseMissing('quote_requests', ['id' => $quote->id]);
    }

    private function sampleQuoteForModel(): array
    {
        return collect($this->sampleQuote())->except('privacy_notice_acknowledged')->all();
    }

    public function test_public_only_sees_published_projects_and_services(): void
    {
        Service::create(['slug'=>'hidden','title'=>'Hidden','summary'=>'Hidden service']);
        Service::create(['slug'=>'live','title'=>'Live','summary'=>'Live service','is_active'=>true]);
        PortfolioProject::create(['slug'=>'draft','title'=>'Draft','summary'=>'Draft project']);
        PortfolioProject::create(['slug'=>'published','title'=>'Published','summary'=>'Published project','is_published'=>true]);
        $this->getJson('/api/services')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/projects')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/projects/draft')->assertNotFound();
        $this->getJson('/api/projects/published')->assertOk();
    }

    public function test_admin_can_create_portfolio_with_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $temp = tempnam(sys_get_temp_dir(), 'portfolio_');
        file_put_contents($temp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO9zOZAAAAAASUVORK5CYII='));
        $file = new UploadedFile($temp, 'demo.png', 'image/png', null, true);
        $result = $this->actingAs($admin)->post('/api/admin/projects', [
            'title' => 'Minta Projekt', 'summary' => 'Éttermi bemutatkozó oldal',
            'is_demo' => true, 'is_published' => true, 'cover_image' => $file,
        ], ['Accept' => 'application/json']);
        $result->assertCreated();
        $project = PortfolioProject::firstOrFail();
        Storage::disk('public')->assertExists($project->cover_image_path);
        @unlink($temp);
    }

    public function test_admin_can_change_site_settings(): void
    {
        $this->actingAs($this->admin())->patchJson('/api/admin/site-settings', [
            'hero_headline' => 'Weboldal neked',
        ])->assertOk();
        $this->getJson('/api/site-settings')->assertJsonPath('data.hero_headline', 'Weboldal neked');
    }
}
