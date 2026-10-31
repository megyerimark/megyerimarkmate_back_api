<?php

namespace App\Http\Controllers;

use App\Support\Stripe;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Rendszerállapot: élesítés utáni önellenőrzés az adminban (Fiók és biztonság fül).
 * Megmutatja, ha elmaradt a migráció, elavult a gyorsítótár, vagy hiányzik egy beállítás –
 * titkot (kulcsot, jelszót) soha nem ad vissza, csak igen/nem állapotot.
 */
class AdminSystemStatusController extends Controller
{
    public const BACKEND_VERSION = 'v21';

    /** Az új funkciók által igényelt táblák – ha valamelyik hiányzik, a migráció nem futott le. */
    private const REQUIRED_TABLES = [
        'finance_entries' => 'Pénzügyek', 'client_sites' => 'Kész projektek', 'admin_settings' => 'Pénzügyi beállítások / széf',
        'stripe_events' => 'Stripe-fizetés', 'maintenance_agreements' => 'Karbantartás', 'project_messages' => 'Ügyfélüzenetek', 'newsletter_subscribers' => 'Hírlevél',
    ];

    public function index(): JsonResponse
    {
        $migrator = app('migrator');
        $pending = [];
        try {
            $ran = $migrator->getRepository()->getRan();
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $pending = array_values(array_diff(array_keys($files), $ran));
        } catch (Throwable) {
            $pending = ['(a migrációs tábla nem olvasható)'];
        }
        $missing = [];
        foreach (self::REQUIRED_TABLES as $table => $feature) {
            if (! Schema::hasTable($table)) $missing[] = ['table' => $table, 'feature' => $feature];
        }
        $schedulerLast = Cache::get('portfolio:scheduler-heartbeat');
        $backupDir = rtrim((string) config('portfolio.backup_path'), '/');
        $backups = collect(array_merge(File::glob($backupDir.'/backup-*.tar.gz'), File::glob($backupDir.'/backup-*.tar.gz.enc')))
            ->sortBy(fn ($f) => basename($f))->values();
        $offsite = Cache::get('portfolio:offsite-backup');
        $stripeError = Cache::get('portfolio:stripe-last-error');
        $verified = Cache::get('portfolio:backup-verified');
        $siteKey = (string) config('services.turnstile.site_key');
        $secretKey = (string) config('services.turnstile.secret_key');
        // Csak az egyik kulcs megadva = hibás beállítás: az űrlapok token nélkül mennek, és a szerver mindet elutasítja (422).
        $turnstileOk = $siteKey !== '' && $secretKey !== '';
        $turnstileHint = match (true) {
            $siteKey !== '' && $secretKey !== '' => 'Bekapcsolva'.(config('services.turnstile.hostnames') ? ' ('.config('services.turnstile.hostnames').').' : '. Ajánlott: TURNSTILE_HOSTNAMES=megyerimarkmate.hu'),
            $siteKey === '' && $secretKey !== '' => 'HIBA: TURNSTILE_SECRET_KEY van, de TURNSTILE_SITE_KEY nincs – minden űrlapbeküldés 422-vel elbukik!',
            $siteKey !== '' && $secretKey === '' => 'TURNSTILE_SITE_KEY van, de TURNSTILE_SECRET_KEY nincs – a widget megjelenik, de a szerver nem ellenőriz.',
            default => 'Kikapcsolva: az űrlapokat csak a kéréskorlát és a csapda-mező védi a spam ellen.',
        };

        $checks = [
            $this->check('migrations', 'Adatbázis-migrációk', $pending === [] && $missing === [],
                $pending === [] && $missing === [] ? 'Minden migráció lefutott.' : 'Hiányzó migráció – futtasd: php artisan migrate --force',
                ['pending' => $pending, 'missing_tables' => $missing]),
            $this->check('debug', 'Hibakereső mód kikapcsolva', ! config('app.debug'),
                config('app.debug') ? 'APP_DEBUG=true – élesben a hibák belső részleteket mutatnak! Állítsd false-ra.' : 'Rendben.'),
            $this->check('https', 'HTTPS és biztonságos süti', str_starts_with((string) config('app.url'), 'https://') && (bool) config('session.secure'),
                'APP_URL https:// legyen, és SESSION_SECURE_COOKIE=true.'),
            $this->check('mail', 'E-mail-küldés', \App\Support\Mailer::canSend() && config('portfolio.admin_email'),
                'PORTFOLIO_MAIL_ENABLED=true, valós MAIL_MAILER és PORTFOLIO_ADMIN_EMAIL szükséges (értesítők, 2FA, ajánlatküldés).'),
            $this->check('two_factor', 'Kötelező kétlépcsős belépés', (bool) config('portfolio.require_admin_2fa'), 'PORTFOLIO_REQUIRE_ADMIN_2FA=true ajánlott élesben.'),
            $this->check('stripe', 'Stripe online fizetés', Stripe::enabled() && (bool) config('services.stripe.webhook_secret') && ! $stripeError,
                (Stripe::enabled() ? (config('services.stripe.webhook_secret') ? 'Beállítva ('.(str_starts_with((string) config('services.stripe.secret'), 'sk_live_') ? 'éles' : 'teszt').' kulcs).' : 'STRIPE_WEBHOOK_SECRET hiányzik – a befizetések nem könyvelődnek!')
                    : 'Nincs beállítva (STRIPE_SECRET üres): az előleg csak átutalással fizethető, elfogadás után nincs átirányítás.')
                .($stripeError ? ' Utolsó Stripe-hiba ('.$stripeError['at'].', '.$stripeError['endpoint'].', HTTP '.$stripeError['status'].'): '.$stripeError['message'].($stripeError['param'] ? ' [mező: '.$stripeError['param'].']' : '') : ''),
                $stripeError ? ['last_error' => $stripeError] : []),
            $this->check('scheduler', 'Ütemezett feladatok (cron)', $schedulerLast && abs(now()->diffInMinutes($schedulerLast)) < 10,
                $schedulerLast ? 'Utolsó futás: '.$schedulerLast : 'A cron-sor nincs beállítva – nincs automatikus mentés, adattörlés és lejárati emlékeztető.'),
            $this->check('backup', 'Napi mentés', $backups->isNotEmpty() && now()->timestamp - filemtime($backups->last()) < 2 * 86400,
                $backups->isNotEmpty() ? 'Legutóbbi: '.basename($backups->last()) : 'Még nincs mentés (portfolio:backup).'),
            $this->check('backup_encrypted', 'Titkosított mentés', (bool) config('portfolio.backup_key'),
                config('portfolio.backup_key') ? 'Rendben. A kulcsot a szerveren kívül is őrizd (jelszókezelő)!' : 'PORTFOLIO_BACKUP_KEY hiányzik – a mentések személyes adatot tartalmaznak titkosítatlanul. Kulcs: php artisan portfolio:backup-key'),
            $this->check('backup_offsite', 'Külső mentés (szerveren kívül)', is_array($offsite) && ($offsite['ok'] ?? false) && abs(now()->diffInHours($offsite['at'])) < 48,
                ! config('portfolio.backup_disk') ? 'Nincs beállítva (PORTFOLIO_BACKUP_DISK): ha a tárhely elvész, a mentés is vele vész.'
                    : (is_array($offsite) ? (($offsite['ok'] ?? false) ? 'Legutóbbi: '.($offsite['file'] ?? '').' ('.$offsite['at'].')' : 'Sikertelen ('.($offsite['error'] ?? 'hiba').', '.$offsite['at'].') – lásd a naplót.') : 'Még nem futott.')),
            $this->check('cache', 'Gyors gyorsítótár', config('cache.default') !== 'database',
                config('cache.default') === 'database' ? 'CACHE_STORE=database: minden kéréskorlát és gyorsítótár a (távoli) MySQL-t terheli. Állítsd CACHE_STORE=file-ra – gyorsabb lesz az oldal.' : 'Rendben ('.config('cache.default').').'),
            $this->check('session_encrypt', 'Titkosított munkamenet', (bool) config('session.encrypt'),
                config('session.encrypt') ? 'Rendben.' : 'SESSION_ENCRYPT=true ajánlott: a munkamenet-adatok titkosítva kerülnek az adatbázisba.'),
            $this->check('log_level', 'Naplózási szint', ! in_array(strtolower((string) config('logging.channels.single.level', 'debug')), ['debug'], true) || ! app()->isProduction(),
                'LOG_LEVEL=warning ajánlott élesben (a debug szint feleslegesen sok, akár személyes adatot is naplózhat).'),
            $this->check('turnstile', 'Robotszűrő (Cloudflare Turnstile)', $turnstileOk,
                $turnstileHint),
            $this->check('proxies', 'Valós IP-cím (proxy beállítás)', (bool) config('portfolio.trusted_proxies') || ! app()->isProduction(),
                config('portfolio.trusted_proxies') ? 'Rendben ('.(config('portfolio.trusted_proxies') === 'cloudflare' ? 'Cloudflare' : 'egyedi lista').').' : 'Ha az oldal Cloudflare mögött van, állítsd: TRUSTED_PROXIES=cloudflare – különben minden látogató a Cloudflare IP-jével jelenik meg, és a kéréskorlátok rosszul működnek.'),
            $this->check('backup_verified', 'Mentés visszaállítási próba', is_array($verified) && ($verified['ok'] ?? false) && abs(now()->diffInDays($verified['at'])) < 10,
                is_array($verified) ? (($verified['ok'] ?? false) ? $verified['message'].' ('.$verified['at'].')' : 'Sikertelen: '.$verified['message'].' ('.$verified['at'].')') : 'Még nem futott: php artisan portfolio:backup-verify (hetente automatikusan fut).'),
            $this->check('storage_link', 'Képtárhely-link', is_link(public_path('storage')) || is_dir(public_path('storage')), 'Futtasd: php artisan storage:link'),
        ];

        return response()->json(['data' => [
            'backend_version' => self::BACKEND_VERSION,
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'routes_cached' => app()->routesAreCached(),
            'config_cached' => app()->configurationIsCached(),
            'database' => $this->databaseOk(),
            'checks' => $checks,
            'ok' => collect($checks)->every(fn ($c) => $c['ok'] || in_array($c['key'], ['stripe', 'backup', 'backup_encrypted', 'backup_offsite', 'backup_verified', 'cache', 'session_encrypt', 'log_level', 'turnstile', 'proxies'], true)),
        ]])->header('Cache-Control', 'no-store');
    }

    private function check(string $key, string $label, bool $ok, string $hint, array $extra = []): array
    {
        return ['key' => $key, 'label' => $label, 'ok' => $ok, 'hint' => $hint] + $extra;
    }

    private function databaseOk(): bool
    {
        try { DB::select('select 1'); return true; } catch (Throwable) { return false; }
    }
}
