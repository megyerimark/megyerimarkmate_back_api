<?php

namespace App\Support;

/**
 * TOTP (RFC 6238) – Google Authenticator, Microsoft Authenticator, 1Password, Bitwarden stb.
 * Külső csomag nélkül: HMAC-SHA1, 30 mp-es lépés, 6 számjegy (az alkalmazások alapértelmezése).
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public const PERIOD = 30;
    public const DIGITS = 6;

    /** 160 bites véletlen titok Base32 kódolással (ezt olvassa be az alkalmazás). */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function code(string $secret, int $timeStep): string
    {
        $key = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N2', 0, $timeStep), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Ellenőrzés ±1 lépés (±30 mp) óraeltérés-tűréssel.
     * @return int|null Az elfogadott időlépés (visszajátszás-védelemhez), vagy null, ha hibás.
     */
    public static function verify(string $secret, string $code, ?int $now = null, int $window = 1): ?int
    {
        if (! preg_match('/\A[0-9]{6}\z/D', $code)) {
            return null;
        }
        $step = intdiv($now ?? time(), self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $step + $i), $code)) {
                return $step + $i;
            }
        }
        return null;
    }

    /** otpauth:// URI – ebből készül a QR-kód. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($account)
            .'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits='.self::DIGITS.'&period='.self::PERIOD;
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function base32Decode(string $text): string
    {
        $text = strtoupper(preg_replace('/[\s=]/', '', $text));
        $bits = '';
        foreach (str_split($text) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                throw new \InvalidArgumentException('Invalid Base32 secret.');
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
