<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMessage extends Model
{
    protected $fillable = ['author', 'body'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    /** Az ügyféltől érkezett, az admin által még nem olvasott üzenetek. */
    public function scopeUnreadFromClient(Builder $query): Builder
    {
        return $query->where('author', 'client')->whereNull('read_at');
    }
}
