<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_totp_secret', 'two_factor_recovery_codes', 'two_factor_totp_last_step'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /** Kell-e második faktor ennél a fióknál (hitelesítő app, saját e-mailes beállítás vagy kötelező szabály). */
    public function requiresTwoFactor(): bool
    {
        return $this->hasTotp() || (bool) $this->two_factor_email_enabled || ($this->is_admin && config('portfolio.require_admin_2fa'));
    }

    /** Be van-e kapcsolva (és megerősítve) a hitelesítő alkalmazásos belépés. Ilyenkor nem megy e-mailes kód. */
    public function hasTotp(): bool
    {
        return $this->two_factor_totp_confirmed_at !== null && (string) $this->two_factor_totp_secret !== '';
    }

    /**
     * Hitelesítő-kód vagy egyszer használatos helyreállítási kód ellenőrzése.
     * Egy TOTP-kód nem használható fel kétszer (visszajátszás ellen), a helyreállítási kód felhasználás után törlődik.
     * @return 'totp'|'recovery'|null
     */
    public function consumeSecondFactor(string $code): ?string
    {
        if (! $this->hasTotp()) {
            return null;
        }
        $code = trim($code);
        if (preg_match('/\A[0-9]{6}\z/D', $code)) {
            $step = \App\Support\Totp::verify($this->two_factor_totp_secret, $code);
            if ($step === null || ($this->two_factor_totp_last_step !== null && $step <= (int) $this->two_factor_totp_last_step)) {
                return null;
            }
            $this->forceFill(['two_factor_totp_last_step' => $step])->save();
            return 'totp';
        }
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        if (strlen($normalized) !== 10) {
            return null;
        }
        $hashes = $this->two_factor_recovery_codes ?? [];
        foreach ($hashes as $i => $hash) {
            if (hash_equals($hash, hash('sha256', $normalized))) {
                unset($hashes[$i]);
                $this->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();
                return 'recovery';
            }
        }
        return null;
    }

    /** Új helyreállítási kódok (10 db, XXXXX-XXXXX). Csak a hash-ük tárolódik; a nyers kódokat egyszer mutatjuk meg. */
    public function regenerateRecoveryCodes(): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codes = [];
        for ($n = 0; $n < 10; $n++) {
            $raw = '';
            for ($i = 0; $i < 10; $i++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5);
        }
        $this->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => hash('sha256', str_replace('-', '', $c)), $codes)])->save();
        return $codes;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'two_factor_email_enabled' => 'boolean',
            'two_factor_totp_secret' => 'encrypted',
            'two_factor_totp_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }
}
