<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('industry', 100)->nullable();
            $table->string('layout_style', 30)->nullable();
            $table->string('font_style', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['industry', 'layout_style', 'font_style']);
        });
    }
};
