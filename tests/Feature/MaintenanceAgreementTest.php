<?php

namespace Tests\Feature;

use App\Models\MaintenanceAgreement;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceAgreementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();
        return $user;
    }

    private function quote(): QuoteRequest
    {
        return QuoteRequest::create([
            'customer_name' => 'Kiss Anna',
            'email' => 'anna@example.com',
            'website_type' => 'business',
            'project_description' => 'Céges weboldal karbantartással.',
            'wants_maintenance' => true,
        ]);
    }

    public function test_maintenance_is_private_and_requires_admin(): void
    {
        $this->getJson('/api/admin/maintenance-agreements')->assertUnauthorized();
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/maintenance-agreements')->assertForbidden();
        $this->actingAs($this->admin())
            ->getJson('/api/admin/maintenance-agreements')->assertOk();
    }

    public function test_draft_can_be_recorded_but_not_activated_before_acceptance(): void
    {
        $quote = $this->quote();
        $this->actingAs($this->admin());
        $this->getJson('/api/admin/maintenance-agreements')
            ->assertOk()->assertJsonPath('data.candidates.0.id', $quote->id);
        $id = $this->postJson('/api/admin/maintenance-agreements', [
            'quote_request_id' => $quote->id,
            'plan_name' => 'Aurora Care Mini',
            'monthly_fee_huf' => 2000,
            'status' => 'draft',
        ])->assertCreated()->assertJsonPath('data.monthly_fee_huf', 2000)->json('data.id');
        $this->postJson('/api/admin/maintenance-agreements', [
            'quote_request_id' => $quote->id,
            'plan_name' => 'Duplicate',
            'monthly_fee_huf' => 2000,
            'status' => 'draft',
        ])->assertUnprocessable();
        $this->patchJson('/api/admin/maintenance-agreements/'.$id, ['status' => 'active'])
            ->assertUnprocessable();
        $this->getJson('/api/admin/dashboard')
            ->assertOk()->assertJsonPath('data.maintenance_leads', 1)
            ->assertJsonPath('data.maintenance_active', 0)
            ->assertJsonPath('data.maintenance_contract_value_huf', 0);

        $quote->forceFill(['status' => 'accepted', 'proposal_accepted_at' => now()])->save();
        $this->patchJson('/api/admin/maintenance-agreements/'.$id, ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.status', 'active');
        $this->getJson('/api/admin/dashboard')
            ->assertOk()->assertJsonPath('data.maintenance_active', 1)
            ->assertJsonPath('data.maintenance_contract_value_huf', 2000);
        $this->assertDatabaseCount('maintenance_agreements', 1);
        $quote->delete();
        $this->assertDatabaseCount('maintenance_agreements', 0);
    }

    public function test_maintenance_api_does_not_change_an_agreement_quote_owner(): void
    {
        $first = $this->quote();
        $second = QuoteRequest::create([
            'customer_name' => 'Nagy Péter', 'email' => 'peter@example.com',
            'website_type' => 'business', 'project_description' => 'Céges oldal.',
        ]);
        $agreement = MaintenanceAgreement::create([
            'quote_request_id' => $first->id, 'plan_name' => 'Mini',
            'monthly_fee_huf' => 2000, 'status' => 'draft',
        ]);
        $this->actingAs($this->admin())->patchJson('/api/admin/maintenance-agreements/'.$agreement->id, [
            'quote_request_id' => $second->id,
        ])->assertUnprocessable();
        $this->assertDatabaseHas('maintenance_agreements', [
            'id' => $agreement->id, 'quote_request_id' => $first->id,
        ]);
    }
}
