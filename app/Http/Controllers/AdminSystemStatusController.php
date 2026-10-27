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
    public const BACKEND_VERSION = 'v18';

    /** Az új funkciók által igényelt táblák – ha valamelyik hiányzik, a migráció nem futott le. */
    private const REQUIRED_TABLES = [
        'finance_entries' => 'Pénzügyek', 'client_sites' => 'Kész projektek', 'admin_settings' => 'Pénzügyi beállítások / széf',
        'stripe_events' => 'Stripe-fizetés', 'maintenance_agreements' => 'Karbantartás', 'project_messages' => 'Ügyfélüzenetek',
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
        $backups = collect(File::glob(rtrim((string) config('portfolio.backup_path'), '/').'/backup-*.tar.gz'))->sort()->values();

        $checks = [
            $this->check('migrations', 'Adatbázis-migrációk', $pending === [] && $missing === [],
                $pending === [] && $missing === [] ? 'Minden migráció lefutott.' : 'Hiányzó migráció – futtasd: php artisan migrate --force',
                ['pending' => $pending, 'missing_tables' => $missing]),
            $this->check('debug', 'Hibakereső mód kikapcsolva', ! config('app.debug'),
                config('app.debug') ? 'APP_DEBUG=true – élesben a hibák belső részleteket mutatnak! Állítsd false-ra.' : 'Rendben.'),
            $this->check('https', 'HTTPS és biztonságos süti', str_starts_with((string) config('app.url'), 'https://') && (bool) config('session.secure'),
                'APP_URL https:// legyen, és SESSION_SECURE_COOKIE=true.'),
            $this->check('mail', 'E-mail-küldés', config('portfolio.mail_enabled') && ! in_array(config('mail.default'), ['log', 'array', 'null'], true) && config('portfolio.admin_email'),
                'PORTFOLIO_MAIL_ENABLED=true, valós MAIL_MAILER és PORTFOLIO_ADMIN_EMAIL szükséges (értesítők, 2FA, ajánlatküldés).'),
            $this->check('two_factor', 'Kötelező kétlépcsős belépés', (bool) config('portfolio.require_admin_2fa'), 'PORTFOLIO_REQUIRE_ADMIN_2FA=true ajánlott élesben.'),
            $this->check('stripe', 'Stripe online fizetés', Stripe::enabled() && (bool) config('services.stripe.webhook_secret'),
                Stripe::enabled() ? (config('services.stripe.webhook_secret') ? 'Beállítva ('.(str_starts_with((string) config('services.stripe.secret'), 'sk_live_') ? 'éles' : 'teszt').' kulcs).' : 'STRIPE_WEBHOOK_SECRET hiányzik – a befizetések nem könyvelődnek!')
                    : 'Nincs beállítva: az előleg csak átutalással fizethető.'),
            $this->check('scheduler', 'Ütemezett feladatok (cron)', $schedulerLast && now()->diffInMinutes($schedulerLast) < 10,
                $schedulerLast ? 'Utolsó futás: '.$schedulerLast : 'A cron-sor nincs beállítva – nincs automatikus mentés, adattörlés és lejárati emlékeztető.'),
            $this->check('backup', 'Napi mentés', $backups->isNotEmpty() && now()->timestamp - filemtime($backups->last()) < 2 * 86400,
                $backups->isNotEmpty() ? 'Legutóbbi: '.basename($backups->last()) : 'Még nincs mentés (portfolio:backup).'),
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
            'ok' => collect($checks)->every(fn ($c) => $c['ok'] || in_array($c['key'], ['stripe', 'backup'], true)),
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
