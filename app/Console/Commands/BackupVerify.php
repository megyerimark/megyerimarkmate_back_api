<?php

namespace App\Console\Commands;

use App\Support\BackupCrypto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use PharData;
use Throwable;

/**
 * Visszaállítási próba: a legutóbbi mentést visszafejti (ha titkosított), megnyitja, és ellenőrzi,
 * hogy benne van-e az adatbázis. A mentés csak akkor ér valamit, ha vissza is lehet állítani belőle.
 * Az eredményt a Rendszerállapot panel mutatja. Ideiglenes fájl nem marad utána.
 *   php artisan portfolio:backup-verify
 */
class BackupVerify extends Command
{
    protected $signature = 'portfolio:backup-verify';
    protected $description = 'A legutóbbi mentés visszaállíthatóságának ellenőrzése (visszafejtés + tartalom)';

    public function handle(): int
    {
        $dir = rtrim((string) config('portfolio.backup_path'), '/');
        $latest = collect(array_merge(File::glob($dir.'/backup-*.tar.gz'), File::glob($dir.'/backup-*.tar.gz.enc')))
            ->sortBy(fn ($f) => basename($f))->last();
        if (! $latest) {
            return $this->result(false, null, 'Nincs mentés (futtasd: php artisan portfolio:backup).');
        }

        $tmp = $dir.'/.verify-'.bin2hex(random_bytes(6)).'.tar.gz';
        try {
            $archivePath = $latest;
            if (str_ends_with($latest, '.enc')) {
                $key = BackupCrypto::parseKey(config('portfolio.backup_key'));
                if ($key === null) {
                    return $this->result(false, $latest, 'A mentés titkosított, de nincs PORTFOLIO_BACKUP_KEY – így nem állítható vissza.');
                }
                BackupCrypto::decryptFile($latest, $tmp, $key);
                $archivePath = $tmp;
            }
            $archive = new PharData($archivePath);
            $db = null;
            foreach (['database.sql', 'database.sqlite'] as $name) {
                if (isset($archive[$name]) && $archive[$name]->getSize() > 0) { $db = $name; break; }
            }
            $files = 0;
            foreach (new \RecursiveIteratorIterator($archive) as $entry) { $files++; }
            unset($archive);
            if ($db === null) {
                return $this->result(false, $latest, 'A mentésből hiányzik az adatbázis.');
            }
            return $this->result(true, $latest, 'Visszaállítható: adatbázis ('.$db.') + '.max(0, $files - 1).' fájl.');
        } catch (Throwable $e) {
            report($e);
            return $this->result(false, $latest, 'A mentés nem nyitható meg: '.$e->getMessage());
        } finally {
            if (is_file($tmp)) @unlink($tmp);
        }
    }

    private function result(bool $ok, ?string $file, string $message): int
    {
        Cache::put('portfolio:backup-verified', [
            'ok' => $ok, 'at' => now()->toDateTimeString(), 'file' => $file ? basename($file) : null, 'message' => $message,
        ], now()->addDays(40));
        $ok ? $this->info($message) : $this->error($message);
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
