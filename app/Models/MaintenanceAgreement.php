<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceAgreement extends Model
{
    protected $fillable = [
        'quote_request_id', 'plan_name', 'monthly_fee_huf', 'status',
        'starts_on', 'next_review_on', 'included_services', 'internal_notes', 'billing_enabled',
    ];

    protected function casts(): array
    {
        return [
            'monthly_fee_huf' => 'integer',
            'starts_on' => 'date:Y-m-d',
            'next_review_on' => 'date:Y-m-d',
            'billing_enabled' => 'boolean',
            'current_period_end' => 'datetime',
            'last_payment_at' => 'datetime',
        ];
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }
}
