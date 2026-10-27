<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FONTOS: az ütemezőhöz a szerveren egy cron-sor kell (lásd README „Ütemezett feladatok”):
// * * * * * cd /útvonal/a/projekthez && php artisan schedule:run >> /dev/null 2>&1

// Életjel: a Rendszerállapot panel ebből látja, hogy a cron-sor fut-e.
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::put('portfolio:scheduler-heartbeat', now()->toDateTimeString(), now()->addDay()))->everyMinute()->name('scheduler-heartbeat');

// Az anonim eseményeket 13 hónapig tartjuk meg, utána törlődnek.
Schedule::call(fn () => \App\Models\SiteEvent::where('created_at', '<', now()->subDays(400))->delete())->daily();

// GDPR: a megőrzési időn túli megkeresések és konzultációs foglalások törlése (Prunable modellek).
Schedule::command('model:prune')->dailyAt('03:15');

// Napi mentés: adatbázis + privát feltöltések, a legutóbbi N példány megtartásával.
Schedule::command('portfolio:backup')->dailyAt('02:30')->withoutOverlapping();

// Domain / tárhely / SSL lejárati emlékeztető (30, 14, 7, 3, 1, 0 nappal előtte).
Schedule::command('portfolio:expiry-reminders')->dailyAt('08:10');
