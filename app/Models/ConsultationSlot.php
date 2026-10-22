<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConsultationSlot extends Model
{
    protected $fillable = ['starts_at', 'duration_minutes'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'duration_minutes' => 'integer']; }
    public function booking(): HasOne { return $this->hasOne(ConsultationBooking::class); }
}
