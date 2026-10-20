<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UniqueSlug
{
    public static function for(string $title, string $modelClass, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'projekt';
        $base = Str::limit($base, 125, '');
        $slug = $base;
        $number = 2;
        while ($modelClass::query()->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$number++;
        }
        return $slug;
    }
}
