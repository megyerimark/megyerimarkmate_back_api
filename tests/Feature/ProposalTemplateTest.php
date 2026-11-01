<?php

namespace Tests\Feature;

use App\Models\ProposalTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();
        return $u;
    }

    public function test_admin_can_manage_templates(): void
    {
        $this->actingAs($this->admin());
        $id = $this->postJson('/api/admin/proposal-templates', [
            'name' => 'Céges weboldal – alap', 'website_type' => 'business', 'price_huf' => 350000, 'deposit_percent' => 30,
            'summary' => '5 aloldalas, reszponzív céges weboldal kapcsolatfelvételi űrlappal.', 'valid_days' => 14,
        ])->assertCreated()->json('data.id');
        $this->getJson('/api/admin/proposal-templates')->assertOk()->assertJsonPath('data.0.price_huf', 350000);
        $this->patchJson("/api/admin/proposal-templates/{$id}", ['price_huf' => 390000])->assertOk()->assertJsonPath('data.price_huf', 390000);
        $this->patchJson("/api/admin/proposal-templates/{$id}", ['deposit_percent' => 150])->assertUnprocessable();
        $this->deleteJson("/api/admin/proposal-templates/{$id}")->assertOk();
        $this->assertSame(0, ProposalTemplate::count());
    }

    public function test_templates_are_admin_only(): void
    {
        $this->getJson('/api/admin/proposal-templates')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/admin/proposal-templates')->assertForbidden();
    }
}
