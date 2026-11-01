<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Az ügyfél nyelve (az oldal nyelve a beküldéskor): ezen a nyelven kapja a leveleket. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_bookings', fn (Blueprint $table) => $table->string('lang', 2)->default('hu'));
        Schema::table('quote_requests', fn (Blueprint $table) => $table->string('lang', 2)->default('hu'));
    }

    public function down(): void
    {
        Schema::table('consultation_bookings', fn (Blueprint $table) => $table->dropColumn('lang'));
        Schema::table('quote_requests', fn (Blueprint $table) => $table->dropColumn('lang'));
    }
};
