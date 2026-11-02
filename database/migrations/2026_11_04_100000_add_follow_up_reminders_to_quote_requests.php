<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            // Utánkövetési emlékeztető (az adminnak): megválaszolatlan megkeresés, illetve válasz nélküli ajánlat – mindkettő egyszer.
            $table->timestamp('reply_reminded_at')->nullable();
            $table->timestamp('quote_reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['reply_reminded_at', 'quote_reminded_at']);
        });
    }
};
