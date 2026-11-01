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
        require_once app_path('Support/mail_text_helpers.php');
    }

    public function boot(): void
    {
        // A nyilvános tartalom gyorsítótárát minden admin-módosítás érvényteleníti.
        foreach ([\App\Models\Service::class, \App\Models\PortfolioProject::class, \App\Models\Testimonial::class, \App\Models\SiteSetting::class] as $model) {
            $model::saved(fn () => \App\Support\PublicCache::flush());
            $model::deleted(fn () => \App\Support\PublicCache::flush());
        }

        Gate::define('access-admin', fn (User $user): bool => (bool) $user->is_admin);

        // Belépés: IP-címenként és e-mail-címenként is korlátozunk, így sok IP-ről
        // (botnet) sem lehet egy fiók jelszavát korlátlanul próbálgatni.
        RateLimiter::for('admin-login', fn (Request $request) => [
            Limit::perMinute(5)->by('ip:'.$request->ip()),
            Limit::perHour(20)->by('email:'.Str::lower((string) $request->input('email'))),
        ]);

        // Globális API-korlát IP-címenként (árasztásos támadások ellen). Az egyes végpontok ennél szigorúbbak.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute((int) config('portfolio.api_rate_per_minute'))->by('api:'.$request->ip()));

        RateLimiter::for('admin-password-reset', fn (Request $request) => [
            Limit::perMinute(3)->by('ip:'.$request->ip()),
            Limit::perHour(5)->by('email:'.Str::lower((string) $request->input('email'))),
        ]);
    }
}
