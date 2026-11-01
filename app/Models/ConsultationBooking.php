<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationBooking extends Model
{
    protected $fillable = ['consultation_slot_id', 'customer_name', 'email', 'phone', 'notes', 'privacy_notice_acknowledged_at', 'lang'];
    /** A kezelőlink kulcsa sosem kerül API-válaszba. */
    protected $hidden = ['manage_token_hash', 'manage_token_encrypted'];

    /**
     * Az ügyfél személyes kezelőlinkje (lemondás / átfoglalás). Ha még nincs, készít egyet.
     * Csak e-mail-küldés előtt hívd: a link csak az ügyfélnek küldött levélben jelenik meg.
     */
    public function manageUrl(): string
    {
        $token = $this->manage_token_encrypted ? \Illuminate\Support\Facades\Crypt::decryptString($this->manage_token_encrypted) : null;
        if (! $token) {
            $token = bin2hex(random_bytes(32));
            $this->forceFill(['manage_token_hash' => hash('sha256', $token), 'manage_token_encrypted' => \Illuminate\Support\Facades\Crypt::encryptString($token)])->save();
        }
        return \App\Support\MailText::frontendLink('/konzultacio/foglalasom', $token, $this);
    }

    public static function byManageToken(string $token): self
    {
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/D', $token), 404);
        return static::with('slot')->where('manage_token_hash', hash('sha256', $token))->firstOrFail();
    }

    protected function casts(): array { return ['privacy_notice_acknowledged_at' => 'datetime', 'reminder_sent_at' => 'datetime']; }
    public function slot(): BelongsTo { return $this->belongsTo(ConsultationSlot::class, 'consultation_slot_id'); }
}
