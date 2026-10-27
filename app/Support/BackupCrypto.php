<?php

namespace App\Support;

use RuntimeException;

/**
 * Mentésfájlok titkosítása libsodium „secretstream”-mel (XChaCha20-Poly1305, hitelesített, darabolt).
 * Nagy fájlnál sem kell a teljes tartalmat memóriába tölteni. A PHP 7.2+ beépítve tartalmazza (ext-sodium).
 *
 * Fájlformátum: "MMBK1" (5 bájt) | fejléc (24 bájt) | [4 bájt hossz + titkosított darab]...
 */
class BackupCrypto
{
    private const MAGIC = 'MMBK1';
    private const CHUNK = 1048576; // 1 MiB

    public static function available(): bool
    {
        return function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push');
    }

    /** Új véletlen kulcs „base64:...” formában (ezt kell a .env-be és a jelszókezelőbe tenni). */
    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES));
    }

    public static function parseKey(?string $key): ?string
    {
        if ($key === null || trim($key) === '') {
            return null;
        }
        $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('PORTFOLIO_BACKUP_KEY érvénytelen. Készíts újat: php artisan portfolio:backup-key');
        }
        return $raw;
    }

    public static function encryptFile(string $source, string $target, string $key): void
    {
        $in = fopen($source, 'rb');
        $out = fopen($target, 'wb');
        if (! $in || ! $out) {
            throw new RuntimeException('A mentésfájl nem nyitható meg titkosításhoz.');
        }
        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            fwrite($out, self::MAGIC.$header);
            $size = filesize($source);
            $read = 0;
            do {
                $chunk = (string) fread($in, self::CHUNK);
                $read += strlen($chunk);
                $tag = $read >= $size ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
                fwrite($out, pack('N', strlen($cipher)).$cipher);
            } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    public static function decryptFile(string $source, string $target, string $key): void
    {
        $in = fopen($source, 'rb');
        if (! $in || fread($in, 5) !== self::MAGIC) {
            throw new RuntimeException('Ez nem titkosított mentésfájl (hiányzó MMBK1 fejléc).');
        }
        $out = fopen($target, 'wb');
        try {
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull((string) fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES), $key);
            $final = false;
            while (! $final) {
                $len = fread($in, 4);
                if ($len === false || strlen($len) !== 4) {
                    throw new RuntimeException('A mentésfájl csonka (hiányzik a vége).');
                }
                $cipher = (string) fread($in, unpack('N', $len)[1]);
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
                if ($result === false) {
                    throw new RuntimeException('Hibás kulcs, vagy a mentésfájl sérült / módosított.');
                }
                [$plain, $tag] = $result;
                fwrite($out, $plain);
                $final = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }
        } catch (\Throwable $e) {
            fclose($out);
            @unlink($target);
            throw $e;
        } finally {
            fclose($in);
            if (is_resource($out)) fclose($out);
        }
    }
}
