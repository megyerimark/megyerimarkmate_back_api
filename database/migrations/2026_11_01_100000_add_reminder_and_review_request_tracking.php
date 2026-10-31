<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Automatikus levelek nyilvántartása: konzultációs emlékeztető és értékeléskérés (mindkettő egyszer megy ki). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_bookings', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable();
        });
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->timestamp('review_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consultation_bookings', fn (Blueprint $table) => $table->dropColumn('reminder_sent_at'));
        Schema::table('quote_requests', fn (Blueprint $table) => $table->dropColumn('review_requested_at'));
    }
};
