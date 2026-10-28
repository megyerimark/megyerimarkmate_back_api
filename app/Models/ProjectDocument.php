<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDocument extends Model
{
    protected $fillable = ['title', 'filename', 'storage_path', 'mime_type', 'size_bytes', 'is_deliverable'];
    protected $casts = ['is_deliverable' => 'boolean'];
    protected $hidden = ['storage_path'];

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }
}
