<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Carbon;

/** Az adminban beállított, százalékos akció állapota (a site_settings kulcsaiból). */
class Promotion
{
    /** @return array{active:bool,percent:int,title:string,ends_at:?string} */
    public static function current(?array $settings = null): array
    {
        $s = $settings ?? SiteSetting::allPublic();
        $percent = (int) ($s['promo_percent'] ?? 0);
        $ends = $s['promo_ends_at'] ?? '';
        $notExpired = $ends === '' || $ends === null || Carbon::parse($ends, 'Europe/Budapest')->endOfDay()->isFuture();
        $active = in_array((string) ($s['promo_enabled'] ?? '0'), ['1', 'true'], true) && $percent > 0 && $notExpired;

        return ['active' => $active, 'percent' => $active ? $percent : 0, 'title' => (string) ($s['promo_title'] ?? ''), 'ends_at' => $ends ?: null];
    }
}
