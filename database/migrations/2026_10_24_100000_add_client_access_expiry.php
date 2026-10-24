<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->timestamp('portal_token_expires_at')->nullable()->after('portal_token_encrypted');
        });
        // Existing private links receive a grace period; none remain perpetual.
        DB::table('quote_requests')->whereNotNull('portal_token_hash')->update([
            'portal_token_expires_at' => now()->addDays(max(1, (int) config('portfolio.client_access_days', 90))),
        ]);
    }

    public function down(): void
    {
        Schema::table('quote_requests', fn (Blueprint $table) => $table->dropColumn('portal_token_expires_at'));
    }
};
