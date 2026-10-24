<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteRequest extends Model
{
    protected $fillable = [
        'customer_name', 'email', 'phone', 'company_name', 'website_type',
        'design_style', 'industry', 'layout_style', 'font_style', 'colors', 'features', 'inspiration_urls',
        'budget_range', 'desired_deadline', 'project_description', 'wants_maintenance', 'consultation_preference',
    ];

    protected $hidden = ['portal_token_hash', 'portal_token_encrypted'];

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order')->orderBy('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProjectMessage::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

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
            'proposal_valid_until' => 'date:Y-m-d',
            'proposal_accepted_at' => 'datetime',
            'portal_token_expires_at' => 'datetime',
            'quoted_price_huf' => 'integer',
        ];
    }
}
