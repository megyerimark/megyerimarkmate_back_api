<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationBooking extends Model
{
    protected $fillable = ['consultation_slot_id', 'customer_name', 'email', 'phone', 'notes', 'privacy_notice_acknowledged_at'];
    protected function casts(): array { return ['privacy_notice_acknowledged_at' => 'datetime']; }
    public function slot(): BelongsTo { return $this->belongsTo(ConsultationSlot::class, 'consultation_slot_id'); }
}
