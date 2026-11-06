<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            // null = a Brevóba még fel kell tölteni a jelenlegi állapotot (megerősítés / leiratkozás után)
            $table->timestamp('brevo_synced_at')->nullable()->after('unsubscribed_at');
            $table->string('brevo_error', 300)->nullable()->after('brevo_synced_at');
            // Üdvözlő sorozat: queued = még fel kell tenni az üdvözlő listára, added = felkerült, skipped = nem kapja
            $table->string('welcome_status', 10)->nullable()->after('brevo_error');
            // true = a weboldalon most erősítette meg (újra) a feliratkozást: egy korábbi Brevo-leiratkozás tiltása feloldható
            $table->boolean('brevo_unblock')->default(false)->after('welcome_status');
            $table->index(['status', 'brevo_synced_at']);
        });

        // A Brevo bekötése előtti feliratkozók nem kapják meg utólag az üdvözlő sorozatot.
        DB::table('newsletter_subscribers')->whereNull('welcome_status')->update(['welcome_status' => 'skipped']);
    }

    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropIndex(['status', 'brevo_synced_at']);
            $table->dropColumn(['brevo_synced_at', 'brevo_error', 'welcome_status', 'brevo_unblock']);
        });
    }
};
