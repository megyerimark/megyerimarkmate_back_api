<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceEntry extends Model
{
    public const VAT_RATES = [0, 5, 18, 27];

    protected $fillable = ['type', 'occurred_on', 'amount_huf', 'vat_rate', 'category', 'description', 'quote_request_id', 'source', 'external_ref'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date:Y-m-d', 'amount_huf' => 'integer', 'vat_rate' => 'integer'];
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    /** Nettó (ÁFA nélküli) összeg forintban. */
    public function netAmount(): int
    {
        return (int) round($this->amount_huf / (1 + $this->vat_rate / 100));
    }

    /** Stripe-ból érkező bevétel rögzítése – ugyanaz a hivatkozás kétszer nem kerül be. */
    public static function recordIncome(string $ref, int $amountHuf, string $category, string $description, ?int $quoteId = null, ?string $date = null): self
    {
        $vat = AdminSetting::finance()['vat_registered'] ? 27 : 0;
        return static::firstOrCreate(['external_ref' => $ref], [
            'type' => 'income', 'occurred_on' => $date ?? now()->toDateString(), 'amount_huf' => $amountHuf,
            'vat_rate' => $vat, 'category' => $category, 'description' => $description,
            'quote_request_id' => $quoteId, 'source' => 'stripe',
        ]);
    }
}
