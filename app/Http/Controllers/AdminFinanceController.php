<?php

namespace App\Http\Controllers;

use App\Models\AdminSetting;
use App\Models\FinanceEntry;
use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pénzügyek: bevételek, kiadások és BECSÜLT adók átalányadós egyéni vállalkozóra.
 * Ez tájékoztató számítás, nem könyvelés és nem bevallás – a végleges adót a könyvelő / NAV-bevallás adja.
 */
class AdminFinanceController extends Controller
{
    public function entries(Request $request): JsonResponse
    {
        $f = $request->validate(['year' => ['required', 'integer', 'min:2020', 'max:2100'], 'month' => ['sometimes', 'integer', 'min:1', 'max:12']]);
        $q = FinanceEntry::whereYear('occurred_on', $f['year']);
        if (isset($f['month'])) $q->whereMonth('occurred_on', $f['month']);
        return response()->json(['data' => $q->orderByDesc('occurred_on')->orderByDesc('id')->limit(1000)->get()])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $entry = FinanceEntry::create($request->validate($this->rules(true)) + ['source' => 'manual']);
        AdminAudit::record($request, 'created', 'finance_entry', $entry->id);
        return response()->json(['data' => $entry], 201);
    }

    public function update(Request $request, FinanceEntry $financeEntry): JsonResponse
    {
        $data = $request->validate($this->rules(false));
        // A Stripe-ból érkezett befizetés összege és dátuma nem írható át (az a valós terhelés).
        if ($financeEntry->source === 'stripe' && array_intersect(array_keys($data), ['type', 'amount_huf', 'occurred_on'])) {
            throw ValidationException::withMessages(['amount_huf' => 'Stripe-befizetésnél csak a kategória, a leírás és az ÁFA-kulcs módosítható.']);
        }
        $financeEntry->update($data);
        AdminAudit::record($request, 'updated', 'finance_entry', $financeEntry->id);
        return response()->json(['data' => $financeEntry]);
    }

    public function destroy(Request $request, FinanceEntry $financeEntry): JsonResponse
    {
        $id = $financeEntry->id;
        $financeEntry->delete();
        AdminAudit::record($request, 'deleted', 'finance_entry', $id);
        return response()->json(['message' => 'Tétel törölve.']);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vat_registered' => ['sometimes', 'boolean'],
            'vat_exempt_limit_huf' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
            'cost_ratio_percent' => ['sometimes', 'numeric', Rule::in([40, 45, 50, 80, 90])],
            'tax_free_income_huf' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'szja_percent' => ['sometimes', 'numeric', 'min:0', 'max:50'],
            'tb_percent' => ['sometimes', 'numeric', 'min:0', 'max:50'],
            'szocho_percent' => ['sometimes', 'numeric', 'min:0', 'max:50'],
            'full_time' => ['sometimes', 'boolean'],
            'min_base_monthly_huf' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'atalany_income_limit_huf' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
        ]);
        $merged = array_merge(AdminSetting::finance(), $data);
        AdminSetting::updateOrCreate(['key' => 'finance'], ['value' => $merged]);
        AdminAudit::record($request, 'updated', 'finance_settings');
        return response()->json(['data' => $merged]);
    }

    public function summary(Request $request): JsonResponse
    {
        $year = (int) $request->validate(['year' => ['required', 'integer', 'min:2020', 'max:2100']])['year'];
        $s = AdminSetting::finance();
        $entries = FinanceEntry::whereYear('occurred_on', $year)->get();
        $lastMonth = $year < (int) now()->year ? 12 : ($year > (int) now()->year ? 0 : (int) now()->month);
        $freeLeft = (int) $s['tax_free_income_huf'];
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $rows = $entries->filter(fn ($e) => (int) $e->occurred_on->month === $m);
            $inc = $rows->where('type', 'income');
            $exp = $rows->where('type', 'expense');
            $incomeGross = (int) $inc->sum('amount_huf');
            $incomeNet = (int) $inc->sum(fn ($e) => $e->netAmount());
            $expenseGross = (int) $exp->sum('amount_huf');
            $expenseNet = (int) $exp->sum(fn ($e) => $e->netAmount());
            $outputVat = $incomeGross - $incomeNet;
            $inputVat = $expenseGross - $expenseNet;
            $vatPayable = $s['vat_registered'] ? $outputVat - $inputVat : 0;

            // Átalányadó: jövedelem = nettó bevétel × (1 − költséghányad). Az éves adómentes rész (a minimálbér fele)
            // az év elejétől fogy el; felette SZJA, és – a rendelkezések szerint – járulék és szocho is.
            $atalanyIncome = (int) round($incomeNet * (1 - $s['cost_ratio_percent'] / 100));
            $freeUsed = min($freeLeft, $atalanyIncome);
            $freeLeft -= $freeUsed;
            $taxable = $atalanyIncome - $freeUsed;
            $active = $m <= $lastMonth;
            // Főállásban havonta legalább a minimum járulékalap után fizetni kell (2026-tól a szocho alapja is 100%).
            $base = $active ? ($s['full_time'] ? max($taxable, (int) $s['min_base_monthly_huf']) : $taxable) : $taxable;
            $szja = (int) round($taxable * $s['szja_percent'] / 100);
            $tb = (int) round($base * $s['tb_percent'] / 100);
            $szocho = (int) round($base * $s['szocho_percent'] / 100);
            $taxes = $szja + $tb + $szocho;
            $expenseCost = $s['vat_registered'] ? $expenseNet : $expenseGross; // AAM-ben a kiadás ÁFA-ja nem igényelhető vissza
            $months[] = [
                'month' => $m, 'income_gross' => $incomeGross, 'income_net' => $incomeNet, 'output_vat' => $outputVat,
                'expense_gross' => $expenseGross, 'expense_net' => $expenseNet, 'input_vat' => $inputVat, 'vat_payable' => $vatPayable,
                'atalany_income' => $atalanyIncome, 'taxable_income' => $taxable, 'szja' => $szja, 'tb' => $tb, 'szocho' => $szocho,
                'taxes_total' => $taxes, 'net_after_tax' => $incomeNet - $taxes, 'net_after_tax_and_expenses' => $incomeNet - $taxes - $expenseCost,
            ];
        }
        $totals = collect($months)->reduce(function ($carry, $row) {
            foreach ($row as $k => $v) if ($k !== 'month') $carry[$k] = ($carry[$k] ?? 0) + $v;
            return $carry;
        }, []);

        $warnings = [];
        if (! $s['vat_registered'] && $s['vat_exempt_limit_huf'] > 0) {
            $ratio = $totals['income_gross'] / $s['vat_exempt_limit_huf'];
            if ($ratio >= 1) $warnings[] = 'Túllépted az alanyi ÁFA-mentesség értékhatárát ('.number_format($s['vat_exempt_limit_huf'], 0, ',', ' ').' Ft). A túllépést okozó számlától ÁFA-t kell felszámítani – azonnal egyeztess a könyvelőddel!';
            elseif ($ratio >= 0.8) $warnings[] = 'Az éves bevétel elérte az alanyi ÁFA-mentességi határ '.(int) round($ratio * 100).'%-át.';
        }
        if ($s['atalany_income_limit_huf'] > 0 && $totals['income_net'] >= 0.8 * $s['atalany_income_limit_huf']) {
            $warnings[] = 'Az éves bevétel megközelíti az átalányadózás bevételi határát ('.number_format($s['atalany_income_limit_huf'], 0, ',', ' ').' Ft).';
        }
        $byCategory = fn (string $type) => $entries->where('type', $type)->groupBy('category')
            ->map(fn ($rows, $cat) => ['category' => $cat, 'total' => (int) $rows->sum('amount_huf')])->sortByDesc('total')->values();

        return response()->json(['data' => [
            'year' => $year, 'settings' => $s, 'months' => $months, 'totals' => $totals, 'warnings' => $warnings,
            'categories' => ['expense' => $byCategory('expense'), 'income' => $byCategory('income')],
        ]])->header('Cache-Control', 'no-store');
    }

    private function rules(bool $create): array
    {
        $s = $create ? 'required' : 'sometimes';
        return [
            'type' => [$s, Rule::in(['income', 'expense'])],
            'occurred_on' => [$s, 'date_format:Y-m-d'],
            'amount_huf' => [$s, 'integer', 'min:1', 'max:999999999'],
            'vat_rate' => ['sometimes', 'integer', Rule::in(FinanceEntry::VAT_RATES)],
            'category' => [$s, 'string', 'min:2', 'max:60'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'quote_request_id' => ['sometimes', 'nullable', 'integer', 'exists:quote_requests,id'],
        ];
    }
}
