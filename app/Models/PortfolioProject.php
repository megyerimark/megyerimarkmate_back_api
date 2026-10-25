<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PortfolioProject extends Model
{
    protected $fillable = [
        'slug', 'title', 'category', 'summary', 'description', 'technologies',
        'project_url', 'is_demo', 'is_published', 'is_featured', 'sort_order', 'results',
    ];

    protected $hidden = ['cover_image_path'];
    protected $appends = ['cover_image_url'];

    protected function casts(): array
    {
        return [
            'technologies' => 'array',
            'results' => 'array',
            'is_demo' => 'boolean',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->cover_image_path
            ? Storage::disk('public')->url($this->cover_image_path)
            : null;
    }
}
