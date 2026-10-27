<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Az előleg nyitott Stripe Checkout munkamenetét eltároljuk, hogy ismételt kattintásnál / több fülön
 * ugyanazt a fizetési oldalt kapja az ügyfél – így nem fizethet kétszer.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('deposit_checkout_session_id', 255)->nullable()->after('deposit_paid_at');
            $table->text('deposit_checkout_url')->nullable()->after('deposit_checkout_session_id');
            $table->timestamp('deposit_checkout_expires_at')->nullable()->after('deposit_checkout_url');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', fn (Blueprint $table) => $table->dropColumn([
            'deposit_checkout_session_id', 'deposit_checkout_url', 'deposit_checkout_expires_at',
        ]));
    }
};
