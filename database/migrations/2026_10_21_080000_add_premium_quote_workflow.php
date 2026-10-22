<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('consultation_preference', 30)->nullable();
            $table->string('portal_token_hash', 64)->nullable()->unique();
            $table->text('portal_token_encrypted')->nullable();
            $table->text('proposal_summary')->nullable();
            $table->date('proposal_valid_until')->nullable();
            $table->timestamp('proposal_accepted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropUnique(['portal_token_hash']);
            $table->dropColumn(['consultation_preference', 'portal_token_hash', 'portal_token_encrypted', 'proposal_summary', 'proposal_valid_until', 'proposal_accepted_at']);
        });
    }
};
