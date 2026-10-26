<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConsultationSlot extends Model
{
    use Prunable;

    protected $fillable = ['starts_at', 'duration_minutes'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'duration_minutes' => 'integer']; }
    public function booking(): HasOne { return $this->hasOne(ConsultationBooking::class); }

    /** A régi, lezajlott időpontok (és a hozzájuk tartozó foglalási adatok) a megőrzési idő után törlődnek. */
    public function prunable(): Builder
    {
        $months = (int) config('portfolio.retention_months');
        return $months > 0
            ? static::query()->where('starts_at', '<', now()->subMonths($months))
            : static::query()->whereRaw('1 = 0');
    }
}
