<?php

namespace App\Console\Commands;

use App\Support\BackupCrypto;
use Illuminate\Console\Command;

/** Új mentéstitkosító kulcs. Nem írja a .env-be: tudatosan kell elmenteni (és a szerveren kívül is!). */
class BackupKey extends Command
{
    protected $signature = 'portfolio:backup-key';
    protected $description = 'Új titkosítókulcs a mentésekhez (PORTFOLIO_BACKUP_KEY)';

    public function handle(): int
    {
        if (! BackupCrypto::available()) {
            $this->error('A PHP sodium bővítménye hiányzik.');
            return self::FAILURE;
        }
        $this->line('PORTFOLIO_BACKUP_KEY='.BackupCrypto::generateKey());
        $this->newLine();
        $this->warn('1) Írd be a fenti sort a szerver .env fájljába.');
        $this->warn('2) Mentsd el a jelszókezelődbe is! Ha a szerverrel együtt elveszik, a mentések NEM állíthatók vissza.');
        return self::SUCCESS;
    }
}
