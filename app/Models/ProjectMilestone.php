<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMilestone extends Model
{
    protected $fillable = ['title', 'description', 'status', 'sort_order', 'due_date'];

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d', 'sort_order' => 'integer'];
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }
}
