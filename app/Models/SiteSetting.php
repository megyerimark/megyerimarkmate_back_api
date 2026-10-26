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
    ];

    public static function allPublic(): array
    {
        return array_replace(self::DEFAULTS, static::query()
            ->whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key')->all());
    }
}
