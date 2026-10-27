<?php

namespace Tests\Feature;

use App\Support\BackupCrypto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EncryptedBackupTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/portfolio-enc-backup-'.uniqid();
        File::ensureDirectoryExists($this->base);
        $db = $this->base.'/db.sqlite';
        $pdo = new \PDO('sqlite:'.$db);
        $pdo->exec("CREATE TABLE secrets (email TEXT); INSERT INTO secrets VALUES ('titkos-ugyfel@example.com');");
        config()->set([
            'portfolio.backup_path' => $this->base.'/backups', 'portfolio.backup_keep' => 2,
            'database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $db],
            'portfolio.backup_key' => BackupCrypto::generateKey(),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    private function runBackup(): void
    {
        $original = config('database.default');
        config()->set('database.default', 'backup_test');
        try {
            $this->artisan('portfolio:backup', ['--no-files' => true])->assertSuccessful();
        } finally {
            config()->set('database.default', $original);
        }
    }

    public function test_backup_is_encrypted_and_round_trips(): void
    {
        $this->runBackup();
        $this->assertCount(0, File::glob($this->base.'/backups/backup-*.tar.gz'), 'Titkosítatlan példány nem maradhat.');
        $enc = File::glob($this->base.'/backups/backup-*.tar.gz.enc');
        $this->assertCount(1, $enc);
        $this->assertStringStartsWith('MMBK1', file_get_contents($enc[0]));

        $out = $this->base.'/restored.tar.gz';
        $this->artisan('portfolio:backup-decrypt', ['file' => $enc[0], '--out' => $out])->assertSuccessful();
        $archive = new \PharData($out);
        $this->assertTrue(isset($archive['database.sqlite']));
        $this->assertStringContainsString('titkos-ugyfel@example.com', file_get_contents($archive['database.sqlite']->getPathname()));
    }

    public function test_wrong_key_or_tampering_is_detected(): void
    {
        $this->runBackup();
        $enc = File::glob($this->base.'/backups/backup-*.tar.gz.enc')[0];
        $this->artisan('portfolio:backup-decrypt', ['file' => $enc, '--out' => $this->base.'/x.tar.gz', '--key' => BackupCrypto::generateKey()])->assertFailed();
        $this->assertFileDoesNotExist($this->base.'/x.tar.gz');

        $bytes = file_get_contents($enc);
        $bytes[60] = chr(ord($bytes[60]) ^ 1);
        file_put_contents($enc, $bytes);
        $this->artisan('portfolio:backup-decrypt', ['file' => $enc, '--out' => $this->base.'/y.tar.gz'])->assertFailed();
    }

    public function test_encrypted_backup_is_uploaded_offsite_and_rotated(): void
    {
        Storage::fake('offsite');
        config()->set(['portfolio.backup_disk' => 'offsite', 'portfolio.backup_disk_keep' => 1]);
        $this->runBackup();
        $this->travel(2)->seconds();
        $this->runBackup();
        $files = Storage::disk('offsite')->files();
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.tar.gz.enc', $files[0]);
        $this->assertTrue(Cache::get('portfolio:offsite-backup')['ok']);
    }

    public function test_unencrypted_backup_is_never_uploaded(): void
    {
        Storage::fake('offsite');
        config()->set(['portfolio.backup_disk' => 'offsite', 'portfolio.backup_key' => null]);
        $original = config('database.default');
        config()->set('database.default', 'backup_test');
        try {
            $this->artisan('portfolio:backup', ['--no-files' => true])->assertFailed();
        } finally {
            config()->set('database.default', $original);
        }
        $this->assertCount(1, File::glob($this->base.'/backups/backup-*.tar.gz'), 'A helyi mentés elkészül.');
        $this->assertSame([], Storage::disk('offsite')->files());
    }
}
