<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PharData;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Napi mentés: adatbázis + feltöltött fájlok egyetlen .tar.gz-be.
 *
 * - MySQL/MariaDB esetén mysqldump-ot használ; ha az a tárhelyen nem elérhető
 *   (pl. tiltott exec), PHP-ból készít SQL-mentést.
 * - SQLite esetén az adatbázisfájlt másolja.
 * - Csak a legutóbbi `portfolio.backup_keep` mentést tartja meg.
 *
 * A mentés személyes adatokat tartalmaz: a mappa ne legyen webről elérhető,
 * és rendszeresen töltsd le egy másik helyre is (a szerverrel együtt elveszhet).
 */
class PortfolioBackup extends Command
{
    protected $signature = 'portfolio:backup {--no-files : Csak az adatbázist menti}';
    protected $description = 'Adatbázis és feltöltött fájlok mentése (.tar.gz), régi mentések törlésével';

    public function handle(): int
    {
        $target = rtrim((string) config('portfolio.backup_path'), '/');
        File::ensureDirectoryExists($target, 0700);
        $stamp = now()->format('Y-m-d_His');
        $work = $target.'/.tmp-'.$stamp;
        File::ensureDirectoryExists($work, 0700);

        try {
            $this->dumpDatabase($work.'/database.sql');
            if (! $this->option('no-files')) {
                foreach (['private' => storage_path('app/private'), 'public' => storage_path('app/public')] as $name => $dir) {
                    if (File::isDirectory($dir)) {
                        File::copyDirectory($dir, $work.'/files/'.$name);
                    }
                }
            }
            $tar = $target.'/backup-'.$stamp.'.tar';
            $archive = new PharData($tar);
            $archive->buildFromDirectory($work);
            $archive->compress(\Phar::GZ);
            unset($archive);
            @unlink($tar);
            @chmod($tar.'.gz', 0600);
            $this->info('Mentés kész: '.$tar.'.gz');
        } catch (Throwable $e) {
            $this->error('A mentés sikertelen: '.$e->getMessage());
            report($e);
            return self::FAILURE;
        } finally {
            File::deleteDirectory($work);
        }

        $this->rotate($target);
        return self::SUCCESS;
    }

    private function dumpDatabase(string $file): void
    {
        $connection = config('database.default');
        $config = config("database.connections.$connection");
        $driver = $config['driver'] ?? '';

        if ($driver === 'sqlite') {
            $path = $config['database'];
            if ($path === ':memory:' || ! is_file($path)) {
                throw new \RuntimeException('Az SQLite adatbázisfájl nem található.');
            }
            File::copy($path, preg_replace('/\.sql$/', '.sqlite', $file));
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true) && $this->tryMysqldump($config, $file)) {
            return;
        }

        $this->phpDump($file);
    }

    private function tryMysqldump(array $config, string $file): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }
        try {
            $process = new Process(array_filter([
                'mysqldump', '--single-transaction', '--quick', '--no-tablespaces', '--skip-comments',
                '--host='.($config['host'] ?? '127.0.0.1'), '--port='.($config['port'] ?? 3306),
                '--user='.($config['username'] ?? ''),
                '--result-file='.$file,
                $config['database'],
            ]), null, ['MYSQL_PWD' => (string) ($config['password'] ?? '')], null, 600);
            $process->run();
            return $process->isSuccessful() && filesize($file) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /** Tartalék: egyszerű, de teljes SQL-mentés PHP-ból (MySQL/MariaDB). */
    private function phpDump(string $file): void
    {
        $pdo = DB::connection()->getPdo();
        $out = fopen($file, 'w');
        fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");
        foreach (DB::select('SHOW TABLES') as $row) {
            $table = array_values((array) $row)[0];
            $create = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
            fwrite($out, "DROP TABLE IF EXISTS `$table`;\n".array_values($create)[1].";\n\n");
            foreach (DB::table($table)->cursor() as $record) {
                $values = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $record);
                fwrite($out, 'INSERT INTO `'.$table.'` VALUES ('.implode(',', $values).");\n");
            }
            fwrite($out, "\n");
        }
        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($out);
    }

    private function rotate(string $target): void
    {
        $files = collect(File::glob($target.'/backup-*.tar.gz'))->sort()->values();
        $keep = (int) config('portfolio.backup_keep');
        foreach ($files->slice(0, max(0, $files->count() - $keep)) as $old) {
            File::delete($old);
        }
    }
}
