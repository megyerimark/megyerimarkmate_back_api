<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // Reverse proxy / tárhely-terheléselosztó mögött is felismerjük a HTTPS-t (HSTS, secure süti).
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', env('TRUSTED_PROXIES')) : null);
        // Anonim eseményszámláló: nincs munkamenet, így CSRF-token sem.
        $middleware->validateCsrfTokens(except: ['api/events', 'api/stripe/webhook']);
        $middleware->alias(['admin.2fa' => \App\Http\Middleware\RequireAdminTwoFactor::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Elmaradt migráció (hiányzó tábla/oszlop) élesítés után: érthető üzenet a „Szerverhiba” helyett.
        $exceptions->render(function (\Illuminate\Database\QueryException $e, Request $request) {
            if (! $request->is('api/*')) return null;
            if (preg_match('/no such table|no such column|Base table or view not found|Unknown column|doesn\'t exist/i', $e->getMessage())) {
                report($e);
                return response()->json([
                    'message' => 'Az adatbázis nincs frissítve az új verzióhoz. A szerveren futtasd: php artisan migrate --force',
                    'error_code' => 'MIGRATION_REQUIRED',
                ], 503);
            }
            return null;
        });
    })->create();
