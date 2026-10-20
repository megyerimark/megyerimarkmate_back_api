<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteRequest extends Model
{
    protected $fillable = [
        'customer_name', 'email', 'phone', 'company_name', 'website_type',
        'design_style', 'colors', 'features', 'inspiration_urls',
        'budget_range', 'desired_deadline', 'project_description', 'wants_maintenance',
    ];

    protected function casts(): array
    {
        return [
            'colors' => 'array',
            'features' => 'array',
            'inspiration_urls' => 'array',
            'desired_deadline' => 'date:Y-m-d',
            'wants_maintenance' => 'boolean',
            'privacy_notice_acknowledged_at' => 'datetime',
            'quoted_at' => 'datetime',
            'quoted_price_huf' => 'integer',
        ];
    }
}
