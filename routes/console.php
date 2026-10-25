<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Az anonim eseményeket 13 hónapig tartjuk meg, utána törlődnek.
Schedule::call(fn () => \App\Models\SiteEvent::where('created_at', '<', now()->subDays(400))->delete())->daily();
