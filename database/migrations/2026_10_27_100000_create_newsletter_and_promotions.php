<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hírlevél: kettős megerősítés (double opt-in), a hozzájárulás bizonyítékával.
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 254)->unique();
            $table->string('status', 16)->default('pending')->index(); // pending | confirmed | unsubscribed
            $table->char('confirm_token_hash', 64)->nullable()->unique();
            $table->char('unsubscribe_token', 64)->unique();
            $table->string('lang', 2)->default('hu');
            $table->string('source', 40)->nullable();
            $table->string('consent_text', 500);
            $table->string('consent_ip', 45)->nullable();
            $table->string('consent_user_agent', 300)->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });

        // Az ajánlatkérés pillanatában érvényes akció (a kedvezmény utólag is visszakereshető).
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('promo_percent')->nullable()->after('budget_range');
            $table->string('promo_title', 80)->nullable()->after('promo_percent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['promo_percent', 'promo_title']);
        });
    }
};
