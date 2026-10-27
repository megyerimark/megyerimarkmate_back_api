<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hitelesítő alkalmazásos (TOTP) kétlépcsős belépés. Ha be van kapcsolva, a belépéshez NEM e-mailes kód kell,
 * így egy feltört e-mail-fiók (jelszó-visszaállítás + kód) önmagában nem elég az admin átvételéhez.
 * A titok és a helyreállítási kódok hash-ei titkosítva (APP_KEY) tárolódnak.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_totp_secret')->nullable()->after('two_factor_email_enabled');
            $table->timestamp('two_factor_totp_confirmed_at')->nullable()->after('two_factor_totp_secret');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_totp_confirmed_at');
            $table->unsignedBigInteger('two_factor_totp_last_step')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'two_factor_totp_secret', 'two_factor_totp_confirmed_at', 'two_factor_recovery_codes', 'two_factor_totp_last_step',
        ]));
    }
};
