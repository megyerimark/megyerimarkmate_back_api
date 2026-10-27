<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemStatusTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();
        return $u;
    }

    public function test_status_reports_checks_without_leaking_secrets(): void
    {
        config()->set(['services.stripe.secret' => 'sk_test_supersecret', 'services.stripe.webhook_secret' => 'whsec_x']);
        $json = $this->actingAs($this->admin())->getJson('/api/admin/system-status')->assertOk()
            ->assertJsonPath('data.checks.0.key', 'migrations')->assertJsonPath('data.checks.0.ok', true)->getContent();
        $this->assertStringNotContainsString('supersecret', $json);
        $this->assertStringNotContainsString('whsec_x', $json);
    }

    public function test_status_is_admin_only(): void
    {
        $this->getJson('/api/admin/system-status')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/admin/system-status')->assertForbidden();
    }

    public function test_missing_table_gives_clear_migration_message_instead_of_500(): void
    {
        $this->actingAs($this->admin());
        Schema::drop('finance_entries');
        $this->getJson('/api/admin/finance/summary?year=2026')->assertStatus(503)->assertJsonPath('error_code', 'MIGRATION_REQUIRED');
        $this->getJson('/api/admin/system-status')->assertOk()->assertJsonPath('data.checks.0.ok', false)
            ->assertJsonPath('data.checks.0.missing_tables.0.table', 'finance_entries');
    }
}
