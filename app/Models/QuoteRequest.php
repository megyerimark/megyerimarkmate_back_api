<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteRequest extends Model
{
    use Prunable;

    protected $fillable = [
        'customer_name', 'email', 'phone', 'company_name', 'website_type',
        'design_style', 'industry', 'layout_style', 'font_style', 'colors', 'features', 'inspiration_urls',
        'budget_range', 'desired_deadline', 'project_description', 'wants_maintenance', 'consultation_preference',
        'source', 'utm_source', 'utm_medium', 'utm_campaign', 'referrer_host', 'landing_path',
    ];

    protected $hidden = ['portal_token_hash', 'portal_token_encrypted', 'deposit_checkout_url', 'final_checkout_url'];

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order')->orderBy('id');
    }

    public function maintenanceAgreement(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MaintenanceAgreement::class);
    }

    /** Az első valódi válasz időpontja (reakcióidő-mérés). Csak egyszer áll be. */
    public function markFirstResponse(): void
    {
        if (! $this->first_response_at) {
            $this->forceFill(['first_response_at' => now()])->save();
        }
    }

    /** Előleg forintban (az ajánlati ár deposit_percent %-a), vagy null, ha nincs előleg. */
    public function depositAmount(): ?int
    {
        if (! $this->deposit_percent || ! $this->quoted_price_huf) return null;
        return (int) round($this->quoted_price_huf * $this->deposit_percent / 100);
    }

    /**
     * A projekt lezárása után még fizetendő összeg forintban. Lezárás után a rögzített érték számít
     * (az ajánlati ár mínusz a ténylegesen befizetett előleg), előtte a várható összeg.
     */
    public function remainingAmount(): int
    {
        if ($this->final_amount_huf !== null) return (int) $this->final_amount_huf;
        $paidDeposit = $this->deposit_paid_at ? (int) $this->depositAmount() : 0;
        return max(0, (int) $this->quoted_price_huf - $paidDeposit);
    }

    /** A kód / átadási csomag átadható-e: a projekt lezárva, és nincs (vagy már rendezve) fennmaradó összeg. */
    public function handoverUnlocked(): bool
    {
        return $this->status === 'completed' && $this->completed_at
            && ($this->remainingAmount() === 0 || $this->final_paid_at !== null);
    }

    /** Akciós ajánlatkérésnél a legmagasabb megengedett ajánlati ár: akció előtti ár − rögzített kedvezmény. */
    public function maxPromoPrice(): ?int
    {
        if (! $this->promo_percent || ! $this->list_price_huf) return null;
        return (int) round($this->list_price_huf * (100 - (int) $this->promo_percent) / 100);
    }

    /** Az ajánlat lényeges tartalmának ujjlenyomata – az elfogadáskor rögzítjük (szerződéses bizonyíték). */
    public function proposalFingerprint(): string
    {
        $fields = [
            $this->id, $this->quoted_price_huf, $this->deposit_percent, $this->proposal_summary,
            $this->proposal_schedule, $this->proposal_terms, $this->proposal_valid_until?->format('Y-m-d'),
        ];
        // Csak akkor kerül bele, ha van: a korábbi (akció előtti ár nélküli) ujjlenyomatok változatlanok maradnak.
        if ($this->list_price_huf !== null) $fields[] = ['list_price_huf' => $this->list_price_huf, 'promo_percent' => $this->promo_percent];
        return hash('sha256', json_encode($fields, JSON_UNESCAPED_UNICODE));
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProjectMessage::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    /**
     * GDPR – korlátozott tárolhatóság. A `php artisan model:prune` (naponta ütemezve) törli
     * azokat a megkereséseket, amelyekhez `portfolio.retention_months` hónapja nem nyúlt senki.
     * Kivétel: elfogadott, folyamatban lévő projekt és élő karbantartási szerződés.
     */
    public function prunable(): Builder
    {
        $months = (int) config('portfolio.retention_months');
        if ($months <= 0) {
            return static::query()->whereRaw('1 = 0');
        }
        $cutoff = now()->subMonths($months);

        return static::query()
            ->where('updated_at', '<', $cutoff)
            ->where('status', '!=', 'accepted')
            ->whereDoesntHave('maintenanceAgreement', fn (Builder $q) => $q->whereIn('status', ['active', 'paused']))
            ->whereDoesntHave('messages', fn (Builder $q) => $q->where('created_at', '>=', $cutoff));
    }

    /** Törlés előtt a feltöltött fájlokat és az árva CRM-jegyzeteket is eltávolítjuk. */
    protected function pruning(): void
    {
        Storage::disk('local')->delete($this->documents()->pluck('storage_path')->all());
        if (! static::where('email', $this->email)->whereKeyNot($this->id)->exists()) {
            StudioClientNote::where('client_email', $this->email)->delete();
        }
    }

    protected function casts(): array
    {
        return [
            'colors' => 'array',
            'features' => 'array',
            'inspiration_urls' => 'array',
            'desired_deadline' => 'date:Y-m-d',
            'wants_maintenance' => 'boolean',
            'privacy_notice_acknowledged_at' => 'datetime',
            'quoted_at' => 'datetime',
            'first_response_at' => 'datetime',
            'follow_up_at' => 'date:Y-m-d',
            'proposal_valid_until' => 'date:Y-m-d',
            'proposal_accepted_at' => 'datetime',
            'portal_token_expires_at' => 'datetime',
            'quoted_price_huf' => 'integer',
            'list_price_huf' => 'integer',
            'deposit_percent' => 'integer',
            'deposit_paid_at' => 'datetime',
            'deposit_checkout_expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'final_amount_huf' => 'integer',
            'final_paid_at' => 'datetime',
            'final_checkout_expires_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'acceptance_evidence' => 'array',
        ];
    }
}
