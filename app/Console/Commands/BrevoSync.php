<?php

namespace App\Console\Commands;

use App\Support\Brevo;
use App\Support\NewsletterSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Hírlevél ↔ Brevo: várakozó feliratkozók feltöltése, a Brevóban leiratkozottak átvezetése. */
class BrevoSync extends Command
{
    protected $signature = 'portfolio:brevo-sync {--full : a Brevo teljes kontaktállományát nézze át a leiratkozásokhoz}';
    protected $description = 'Hírlevél-feliratkozók szinkronja a Brevóval';

    public function handle(): int
    {
        if (! Brevo::configured()) {
            $this->info('A BREVO_API_KEY és a BREVO_LIST_ID nincs beállítva – kihagyva.');
            return self::SUCCESS;
        }
        try {
            $r = NewsletterSync::run((bool) $this->option('full'));
        } catch (Throwable $e) {
            Log::warning('Brevo sync failed', ['error' => mb_substr($e->getMessage(), 0, 300)]);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->info("Feltöltve: {$r['synced']}, hibás: {$r['failed']}, Brevóból átvezetett leiratkozás: {$r['unsubscribed']}.");
        return $r['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
