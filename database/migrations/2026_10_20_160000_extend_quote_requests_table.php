<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->timestamp('privacy_notice_acknowledged_at')->nullable();
            $table->unsignedInteger('quoted_price_huf')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['email']);
            $table->dropColumn(['privacy_notice_acknowledged_at', 'quoted_price_huf', 'quoted_at']);
        });
    }
};
