<?php

namespace App\Console\Commands;

use App\Support\BackupCrypto;
use Illuminate\Console\Command;
use Throwable;

/**
 * Titkosított mentés visszafejtése visszaállításhoz:
 *   php artisan portfolio:backup-decrypt storage/app/backups/backup-2026-10-28_023000.tar.gz.enc
 * Másik gépen (ahol nincs .env): --key="base64:..."
 */
class BackupDecrypt extends Command
{
    protected $signature = 'portfolio:backup-decrypt {file : A .tar.gz.enc fájl} {--out= : Kimeneti fájl (alapból .enc nélkül)} {--key= : Kulcs, ha nem a .env-ből jön}';
    protected $description = 'Titkosított mentés (.tar.gz.enc) visszafejtése .tar.gz-be';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error('A fájl nem található: '.$file);
            return self::FAILURE;
        }
        $out = (string) ($this->option('out') ?: preg_replace('/\.enc$/', '', $file));
        if ($out === $file || file_exists($out)) {
            $this->error('A kimeneti fájl már létezik vagy azonos a bemenettel: '.$out);
            return self::FAILURE;
        }
        try {
            $key = BackupCrypto::parseKey($this->option('key') ?: config('portfolio.backup_key'));
            if ($key === null) {
                $this->error('Nincs kulcs: add meg --key="base64:..." formában, vagy állítsd be a PORTFOLIO_BACKUP_KEY-t.');
                return self::FAILURE;
            }
            BackupCrypto::decryptFile($file, $out, $key);
            @chmod($out, 0600);
        } catch (Throwable $e) {
            $this->error('A visszafejtés sikertelen: '.$e->getMessage());
            return self::FAILURE;
        }
        $this->info('Kész: '.$out.'  →  kicsomagolás: tar -xzf '.basename($out));
        return self::SUCCESS;
    }
}
