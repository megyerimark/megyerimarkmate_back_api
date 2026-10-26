<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user): bool => (bool) $user->is_admin);

        // Belépés: IP-címenként és e-mail-címenként is korlátozunk, így sok IP-ről
        // (botnet) sem lehet egy fiók jelszavát korlátlanul próbálgatni.
        RateLimiter::for('admin-login', fn (Request $request) => [
            Limit::perMinute(5)->by('ip:'.$request->ip()),
            Limit::perHour(20)->by('email:'.Str::lower((string) $request->input('email'))),
        ]);

        RateLimiter::for('admin-password-reset', fn (Request $request) => [
            Limit::perMinute(3)->by('ip:'.$request->ip()),
            Limit::perHour(5)->by('email:'.Str::lower((string) $request->input('email'))),
        ]);
    }
}
