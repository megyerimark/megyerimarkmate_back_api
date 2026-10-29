<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'site_title' => 'Megyeri Márk | Webfejlesztés',
        'hero_headline' => 'Ne csak jelen legyél online. Emelkedj ki.',
        'hero_description' => 'Egyedi weboldalakat és prémium digitális megoldásokat készítek vállalkozásoknak – hogy a megjelenésed bizalmat építsen, és támogassa az ügyfélszerzést.',
        'meta_title' => 'Megyeri Márk | Prémium webfejlesztés vállalkozásoknak',
        'meta_description' => 'Prémium, egyedi weboldalak vállalkozásoknak. Megyeri Márk webfejlesztés: modern megjelenés, reszponzív kivitelezés és ügyfélszerzést támogató megoldások.',
        'og_image_url' => '',
        'contact_email' => '',
        'contact_phone' => '',
        'instagram_url' => '',
        'linkedin_url' => '',
        'github_url' => '',
        // Trustindex (Google-vélemények) widget azonosítója. Üresen nincs külső widget és nincs süti-kérdés.
        'reviews_widget_id' => '',
        // A Google Cégprofil véleményírási linkje (pl. https://g.page/r/.../review).
        'google_review_url' => '',
        // Akció (admin → Oldalbeállítások → Akció). A főoldalon és az árkalkulátorban jelenik meg.
        'promo_enabled' => '0',
        'promo_percent' => '0',
        'promo_title' => '',
        'promo_title_en' => '',
        'promo_text' => '',
        'promo_text_en' => '',
        'promo_ends_at' => '',
        // Megjelenés (admin → Oldalbeállítások → Megjelenés): az egész oldal színvilága.
        'site_palette' => 'nightblue',
        // Automatikus ünnepi téma: a megadott időszakban felülírja az alaptémát (a dátumot a böngésző számolja).
        'seasonal_enabled' => '1',
        'seasonal_christmas' => '1',
        'seasonal_easter' => '1',
        'seasonal_valentine' => '1',
        'seasonal_national' => '1',
    ];

    /** Választható alaptémák (az ünnepi témák automatikusan kapcsolnak, kézzel nem választhatók alapnak). */
    public const PALETTES = ['nightblue', 'graphite', 'aurora'];
    public const SEASONAL_FLAGS = ['seasonal_enabled', 'seasonal_christmas', 'seasonal_easter', 'seasonal_valentine', 'seasonal_national'];

    public static function allPublic(): array
    {
        return array_replace(self::DEFAULTS, static::query()
            ->whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key')->all());
    }
}
