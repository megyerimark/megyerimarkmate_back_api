<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Konzultáció önkiszolgálás: személyes kezelőlink (lemondás / átfoglalás) a foglaláshoz. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_bookings', function (Blueprint $table) {
            $table->string('manage_token_hash', 64)->nullable()->unique();
            $table->text('manage_token_encrypted')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consultation_bookings', function (Blueprint $table) {
            $table->dropUnique(['manage_token_hash']);
            $table->dropColumn(['manage_token_hash', 'manage_token_encrypted']);
        });
    }
};
