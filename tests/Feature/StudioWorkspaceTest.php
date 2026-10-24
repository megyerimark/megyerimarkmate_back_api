<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        return $admin;
    }

    private function quote(): QuoteRequest
    {
        return QuoteRequest::create([
            'customer_name' => 'Kiss Anna',
            'email' => 'anna@example.com',
            'website_type' => 'business',
            'project_description' => 'Üzleti weboldal fejlesztése',
        ]);
    }

    public function test_workspace_is_admin_only(): void
    {
        foreach (['/api/admin/quote-board', '/api/admin/workspace/tasks', '/api/admin/workspace/client-notes?email=anna%40example.com'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->actingAs(User::factory()->create())->getJson('/api/admin/workspace/tasks')->assertForbidden();
    }

    public function test_board_and_valid_status_transitions(): void
    {
        $quote = $this->quote();
        $this->actingAs($this->admin())->getJson('/api/admin/quote-board')
            ->assertOk()->assertJsonPath('data.totals.new', 1)->assertJsonPath('data.columns.new.0.id', $quote->id);
        $this->patchJson('/api/admin/quote-requests/'.$quote->id, ['status' => 'accepted'])->assertUnprocessable();
        $this->patchJson('/api/admin/quote-requests/'.$quote->id, ['status' => 'quoted'])->assertUnprocessable();
        $this->patchJson('/api/admin/quote-requests/'.$quote->id, ['status' => 'completed'])->assertUnprocessable();
        $this->patchJson('/api/admin/quote-requests/'.$quote->id, ['status' => 'reviewing'])->assertOk();
        $this->getJson('/api/admin/quote-board')->assertJsonPath('data.totals.reviewing', 1);
    }

    public function test_admin_can_manage_tasks_and_associate_enquiries(): void
    {
        $quote = $this->quote();
        $this->actingAs($this->admin());
        $this->postJson('/api/admin/workspace/tasks', ['title' => 'Feladat', 'quote_request_id' => 99999])->assertUnprocessable();
        $created = $this->postJson('/api/admin/workspace/tasks', [
            'title' => 'Ügyfél visszahívása', 'due_date' => now()->addDays(2)->toDateString(), 'quote_request_id' => $quote->id,
        ])->assertCreated()->assertJsonPath('data.is_done', false)->json('data.id');
        $this->getJson('/api/admin/workspace/tasks?state=open')->assertJsonCount(1, 'data');
        $this->patchJson('/api/admin/workspace/tasks/'.$created, ['is_done' => true])->assertOk()->assertJsonPath('data.is_done', true);
        $this->getJson('/api/admin/workspace/tasks?state=open')->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/workspace/tasks?state=done')->assertJsonCount(1, 'data');
        $this->deleteJson('/api/admin/workspace/tasks/'.$created)->assertOk();
        $this->assertDatabaseMissing('studio_tasks', ['id' => $created]);
    }

    public function test_client_notes_remain_private_and_are_removed_with_last_enquiry(): void
    {
        $quote = $this->quote();
        $this->actingAs($this->admin());
        $this->postJson('/api/admin/workspace/client-notes', ['email' => 'unknown@example.com', 'body' => 'Jegyzet'])->assertUnprocessable();
        $id = $this->postJson('/api/admin/workspace/client-notes', [
            'email' => $quote->email, 'body' => 'Hétfőn visszahívni.',
        ])->assertCreated()->json('data.id');
        $this->getJson('/api/admin/workspace/client-notes?email=anna%40example.com')
            ->assertOk()->assertJsonPath('data.0.body', 'Hétfőn visszahívni.');
        $this->deleteJson('/api/admin/quote-requests/'.$quote->id)->assertOk();
        $this->assertDatabaseMissing('studio_client_notes', ['id' => $id]);
    }

    public function test_accepted_project_and_outgoing_proposal_appear_in_workspace(): void
    {
        $quote = $this->quote();
        $quote->status = 'accepted';
        $quote->quoted_at = now();
        $quote->proposal_accepted_at = now();
        $quote->quoted_price_huf = 350000;
        $quote->save();
        $this->actingAs($this->admin())->getJson('/api/admin/workspace/projects')
            ->assertOk()->assertJsonPath('data.0.id', $quote->id)
            ->assertJsonPath('data.0.milestones_count', 0);
        $this->getJson('/api/admin/workspace/communications')
            ->assertOk()->assertJsonPath('data.proposals.0.id', $quote->id);
        $this->getJson('/api/admin/dashboard')
            ->assertOk()->assertJsonPath('data.accepted_proposals_value_huf', 350000);
    }
}
