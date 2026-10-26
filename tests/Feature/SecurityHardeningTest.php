<?php

namespace Tests\Feature;

use App\Mail\AdminLoginCode;
use App\Mail\CustomerQuoteConfirmation;
use App\Models\ConsultationSlot;
use App\Models\MaintenanceAgreement;
use App\Models\PortfolioProject;
use App\Models\QuoteRequest;
use App\Models\StudioClientNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    private function quote(array $overrides = []): QuoteRequest
    {
        $quote = new QuoteRequest([
            'customer_name' => 'Régi Ügyfél', 'email' => 'regi@example.com', 'website_type' => 'business',
            'project_description' => 'Egy régi megkeresés leírása.',
        ]);
        $quote->forceFill(array_merge(['status' => 'rejected', 'privacy_notice_acknowledged_at' => now()], $overrides));
        $quote->save();
        return $quote;
    }

    public function test_required_two_factor_sends_code_even_if_admin_did_not_enable_it(): void
    {
        $this->withHeader('Origin', config('app.url'));
        Mail::fake();
        config()->set(['portfolio.require_admin_2fa' => true, 'portfolio.mail_enabled' => true, 'mail.default' => 'smtp']);
        $admin = $this->admin();

        $this->postJson('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertStatus(202)->assertJsonPath('requires_two_factor', true);
        $this->getJson('/api/admin/me')->assertForbidden();
        Mail::assertSent(AdminLoginCode::class);
    }

    public function test_required_two_factor_cannot_be_switched_off(): void
    {
        $this->withHeader('Origin', config('app.url'));
        config()->set('portfolio.require_admin_2fa', true);
        $admin = $this->admin();
        $this->actingAs($admin)->withSession(['admin_two_factor_verified' => $admin->id])
            ->putJson('/api/admin/security/two-factor', ['enabled' => false, 'current_password' => 'password'])
            ->assertStatus(422);
        $this->getJson('/api/admin/security')->assertJsonPath('data.two_factor_required', true);
    }

    public function test_required_two_factor_without_working_mail_refuses_login(): void
    {
        config()->set(['portfolio.require_admin_2fa' => true, 'portfolio.mail_enabled' => false]);
        $admin = $this->admin();
        $this->postJson('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertStatus(503);
        $this->assertGuest('web');
    }

    public function test_login_is_limited_per_email_across_many_ip_addresses(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 20; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.($i + 1)])
                ->postJson('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.99'])
            ->postJson('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertStatus(429);
    }

    public function test_api_responses_carry_security_headers(): void
    {
        $this->getJson('/api/services')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy');
        $this->get('https://localhost/api/services')->assertHeader('Strict-Transport-Security');
    }

    public function test_customer_mail_is_still_delivered_after_response(): void
    {
        Mail::fake();
        config()->set(['portfolio.mail_enabled' => true, 'mail.default' => 'smtp', 'portfolio.customer_receipt_enabled' => true]);
        $this->postJson('/api/quote-requests', [
            'customer_name' => 'Teszt', 'email' => 't@example.com', 'website_type' => 'business',
            'project_description' => 'Céges weboldal kellene.', 'privacy_notice_acknowledged' => true,
        ])->assertCreated();
        Mail::assertSent(CustomerQuoteConfirmation::class);
    }

    public function test_old_inactive_enquiries_are_pruned_but_active_work_is_kept(): void
    {
        Storage::fake('local');
        config()->set('portfolio.retention_months', 24);
        $this->travelTo(now()->subMonths(30));
        $old = $this->quote();
        Storage::disk('local')->put('client-projects/'.$old->id.'/a.pdf', 'x');
        $old->documents()->create(['title' => 'Terv', 'filename' => 'a.pdf', 'storage_path' => 'client-projects/'.$old->id.'/a.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1]);
        StudioClientNote::create(['client_email' => 'regi@example.com', 'body' => 'jegyzet']);
        $accepted = $this->quote(['email' => 'aktiv@example.com', 'status' => 'accepted']);
        $maintained = $this->quote(['email' => 'karb@example.com', 'status' => 'completed']);
        MaintenanceAgreement::create(['quote_request_id' => $maintained->id, 'plan_name' => 'Alap', 'monthly_fee_huf' => 10000, 'status' => 'active']);
        $oldSlot = ConsultationSlot::create(['starts_at' => now(), 'duration_minutes' => 30]);
        $this->travelBack();
        $fresh = $this->quote(['email' => 'uj@example.com']);

        Artisan::call('model:prune', ['--model' => [QuoteRequest::class, ConsultationSlot::class]]);

        $this->assertModelMissing($old);
        $this->assertModelMissing($oldSlot);
        $this->assertModelExists($accepted);
        $this->assertModelExists($maintained);
        $this->assertModelExists($fresh);
        Storage::disk('local')->assertMissing('client-projects/'.$old->id.'/a.pdf');
        $this->assertDatabaseMissing('studio_client_notes', ['client_email' => 'regi@example.com']);
    }

    public function test_sitemap_lists_static_pages_and_only_published_real_projects(): void
    {
        PortfolioProject::create(['slug' => 'valodi', 'title' => 'Valódi', 'summary' => 'x', 'is_published' => true, 'is_demo' => false]);
        PortfolioProject::create(['slug' => 'rejtett', 'title' => 'Rejtett', 'summary' => 'x', 'is_published' => false, 'is_demo' => false]);
        $xml = $this->get('/api/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString('/munkak/valodi', $xml);
        $this->assertStringContainsString('/impresszum', $xml);
        $this->assertStringNotContainsString('rejtett', $xml);
    }

    public function test_backup_command_creates_archive_and_rotates(): void
    {
        $base = sys_get_temp_dir().'/portfolio-backup-test-'.uniqid();
        File::ensureDirectoryExists($base);
        $db = $base.'/db.sqlite';
        File::put($db, '');
        $original = config('database.default');
        config()->set(['portfolio.backup_path' => $base.'/backups', 'portfolio.backup_keep' => 1,
            'database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $db]]);
        try {
            config()->set('database.default', 'backup_test');
            $this->artisan('portfolio:backup', ['--no-files' => true])->assertSuccessful();
            $this->travel(1)->seconds();
            $this->artisan('portfolio:backup', ['--no-files' => true])->assertSuccessful();
        } finally {
            config()->set('database.default', $original);
        }
        $this->assertCount(1, File::glob($base.'/backups/backup-*.tar.gz'));
        File::deleteDirectory($base);
    }

    public function test_global_api_rate_limit_per_ip(): void
    {
        config()->set('portfolio.api_rate_per_minute', 30);
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/site-settings')->assertOk();
        }
        $this->getJson('/api/site-settings')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])->getJson('/api/site-settings')->assertOk();
    }
}
