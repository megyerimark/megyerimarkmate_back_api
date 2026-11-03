<?php

namespace App\Console\Commands;

use App\Support\GoogleReviews;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Naponta: a Google Cégprofil értékelésének és legfrissebb véleményeinek lekérése (Places API). */
class GoogleReviewsRefresh extends Command
{
    protected $signature = 'portfolio:google-reviews';
    protected $description = 'Google-vélemények frissítése a Cégprofilból (Places API)';

    public function handle(): int
    {
        if (! GoogleReviews::configured()) {
            $this->info('A GOOGLE_PLACES_API_KEY és a GOOGLE_PLACE_ID nincs beállítva – kihagyva.');
            return self::SUCCESS;
        }
        try {
            $data = GoogleReviews::refresh();
        } catch (Throwable $e) {
            Log::warning('Google reviews refresh failed', ['error' => mb_substr($e->getMessage(), 0, 300)]);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->info('Frissítve: '.($data['rating'] ?? '–').' ('.$data['count'].' értékelés), '.count($data['reviews']).' vélemény.');
        return self::SUCCESS;
    }
}
