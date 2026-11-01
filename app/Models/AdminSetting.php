<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Privát admin-beállítások (kulcs–JSON). Ezek SOSEM kerülnek a nyilvános API-ra. */
class AdminSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /**
     * Pénzügyi beállítások 2026-os alapértékekkel (átalányadós egyéni vállalkozó).
     * Források: NAV / NGM 2026-os adócsomag; ellenőrizd a könyvelőddel.
     */
    public static function financeDefaults(): array
    {
        return [
            'tax_regime' => 'atalanyado',
            'vat_registered' => false,           // alanyi adómentes (AAM)
            'vat_exempt_limit_huf' => 20_000_000, // AAM értékhatár 2026
            'cost_ratio_percent' => 45,           // általános költséghányad 2026 (2027-től 50%)
            'tax_free_income_huf' => 1_936_800,   // a 2026-os éves minimálbér fele
            'szja_percent' => 15,
            'tb_percent' => 18.5,
            'szocho_percent' => 13,
            'full_time' => true,                  // főállású: havi minimum járulékalap
            'min_base_monthly_huf' => 373_200,    // garantált bérminimum 2026 (minimálbér: 322 800)
            'atalany_income_limit_huf' => 38_736_000, // bevételi határ 2026
        ];
    }

    public static function finance(): array
    {
        $stored = static::find('finance')?->value ?? [];
        return array_merge(static::financeDefaults(), array_intersect_key($stored, static::financeDefaults()));
    }

    /** Konzultáció: állandó videóhívás-link (Google Meet, Jitsi, Zoom…) – csak a foglalóknak küldött levelekben jelenik meg. */
    public static function consultation(): array
    {
        $stored = static::find('consultation')?->value ?? [];
        return ['meeting_url' => is_string($stored['meeting_url'] ?? null) && $stored['meeting_url'] !== '' ? $stored['meeting_url'] : null];
    }
}
