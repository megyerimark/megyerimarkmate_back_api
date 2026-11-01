<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalTemplate extends Model
{
    protected $fillable = ['name', 'website_type', 'price_huf', 'deposit_percent', 'summary', 'schedule', 'terms', 'valid_days'];
    protected function casts(): array
    {
        return ['price_huf' => 'integer', 'deposit_percent' => 'integer', 'valid_days' => 'integer'];
    }
}
