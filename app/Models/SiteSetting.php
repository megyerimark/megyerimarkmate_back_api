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
        'contact_email' => '',
        'contact_phone' => '',
        'instagram_url' => '',
        'linkedin_url' => '',
        'github_url' => '',
    ];

    public static function allPublic(): array
    {
        return array_replace(self::DEFAULTS, static::query()
            ->whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key')->all());
    }
}
