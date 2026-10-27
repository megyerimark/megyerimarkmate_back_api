<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * A nyilvános tartalom (szolgáltatások, referenciák, beállítások, vélemények) rövid gyorsítótára.
 * A távoli MySQL-szerverhez (Rackhost) minden lekérdezés hálózati kör; így a főoldal API-hívásai
 * jellemzően adatbázis nélkül válaszolnak. Bármely admin-módosítás azonnal érvényteleníti (verziószám).
 */
class PublicCache
{
    private const VERSION_KEY = 'public-content:version';
    private const TTL_SECONDS = 600;

    public static function remember(string $key, Closure $callback): mixed
    {
        try {
            $version = Cache::get(self::VERSION_KEY, 1);
            return Cache::remember("public-content:v{$version}:{$key}", self::TTL_SECONDS, $callback);
        } catch (Throwable) {
            return $callback(); // a gyorsítótár hibája sosem töri el az oldalt
        }
    }

    public static function flush(): void
    {
        try {
            Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 1) + 1);
        } catch (Throwable) {
            // nincs teendő: legfeljebb 10 percig a régi tartalom látszik
        }
    }
}
