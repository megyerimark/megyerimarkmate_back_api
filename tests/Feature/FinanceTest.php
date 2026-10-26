<?php

namespace Tests\Feature;

use App\Models\FinanceEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();
        $this->actingAs($u);
        $this->travelTo(now()->setDate(2026, 3, 15));
    }

    public function test_atalanyado_estimate_uses_cost_ratio_tax_free_part_and_minimum_base(): void
    {
        // Január: 1 000 000 Ft bevétel (AAM, ÁFA nélkül) → jövedelem 550 000 (45% költséghányad), teljesen adómentes.
        $this->postJson('/api/admin/finance/entries', ['type' => 'income', 'occurred_on' => '2026-01-10', 'amount_huf' => 1000000, 'category' => 'Weboldal'])->assertCreated();
        // Február: 4 000 000 Ft → jövedelem 2 200 000; a maradék adómentes rész 1 386 800 → adóköteles 813 200.
        $this->postJson('/api/admin/finance/entries', ['type' => 'income', 'occurred_on' => '2026-02-10', 'amount_huf' => 4000000, 'category' => 'Weboldal'])->assertCreated();
        $this->postJson('/api/admin/finance/entries', ['type' => 'expense', 'occurred_on' => '2026-02-11', 'amount_huf' => 127000, 'vat_rate' => 27, 'category' => 'Szoftver'])->assertCreated();

        $d = $this->getJson('/api/admin/finance/summary?year=2026')->assertOk()->json('data');
        [$jan, $feb, $mar, $apr] = array_slice($d['months'], 0, 4);
        $this->assertSame(550000, $jan['atalany_income']);
        $this->assertSame(0, $jan['taxable_income']);
        $this->assertSame(0, $jan['szja']);
        $this->assertSame((int) round(373200 * 0.185), $jan['tb']); // főállás: minimum járulékalap
        $this->assertSame(813200, $feb['taxable_income']);
        $this->assertSame((int) round(813200 * 0.15), $feb['szja']);
        $this->assertSame((int) round(813200 * 0.185), $feb['tb']);
        $this->assertSame(127000, $feb['expense_gross']);
        $this->assertSame(100000, $feb['expense_net']);
        // AAM: a kiadás teljes bruttó összege költség.
        $this->assertSame($feb['net_after_tax'] - 127000, $feb['net_after_tax_and_expenses']);
        $this->assertGreaterThan(0, $mar['tb']);  // aktuális hónap: minimum járulék
        $this->assertSame(0, $apr['tb']);         // jövőbeli hónapra nem becsül
    }

    public function test_vat_registered_mode_and_aam_warning(): void
    {
        $this->putJson('/api/admin/finance/settings', ['vat_registered' => true])->assertOk();
        $this->postJson('/api/admin/finance/entries', ['type' => 'income', 'occurred_on' => '2026-01-05', 'amount_huf' => 1270000, 'vat_rate' => 27, 'category' => 'Weboldal']);
        $jan = $this->getJson('/api/admin/finance/summary?year=2026')->json('data.months.0');
        $this->assertSame(1000000, $jan['income_net']);
        $this->assertSame(270000, $jan['vat_payable']);

        $this->putJson('/api/admin/finance/settings', ['vat_registered' => false])->assertOk();
        $this->postJson('/api/admin/finance/entries', ['type' => 'income', 'occurred_on' => '2026-02-05', 'amount_huf' => 19000000, 'category' => 'Weboldal']);
        $warnings = $this->getJson('/api/admin/finance/summary?year=2026')->json('data.warnings');
        $this->assertStringContainsString('Túllépted', implode(' ', $warnings));
    }

    public function test_stripe_entries_amount_is_locked_and_settings_validated(): void
    {
        $e = FinanceEntry::recordIncome('stripe:in_1', 15000, 'Karbantartás', 'Havidíj');
        $this->patchJson('/api/admin/finance/entries/'.$e->id, ['amount_huf' => 1])->assertUnprocessable();
        $this->patchJson('/api/admin/finance/entries/'.$e->id, ['category' => 'Karbantartási díj'])->assertOk();
        $this->putJson('/api/admin/finance/settings', ['cost_ratio_percent' => 33])->assertUnprocessable();
    }
}
