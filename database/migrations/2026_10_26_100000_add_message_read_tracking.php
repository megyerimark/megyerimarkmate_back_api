<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_messages', function (Blueprint $table) {
            // Mikor olvasta az admin az ügyfél üzenetét (null = olvasatlan).
            $table->timestamp('read_at')->nullable()->after('body');
            $table->index(['author', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('project_messages', function (Blueprint $table) {
            $table->dropIndex(['author', 'read_at']);
            $table->dropColumn('read_at');
        });
    }
};
