<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projektlezárás: a „kész” jelöléskor végleges e-mail megy az ügyfélnek a fennmaradó összeggel,
 * a kód (átadási csomag) pedig csak a fennmaradó összeg kifizetése után adható át.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('deposit_checkout_expires_at');
            // A lezáráskor rögzített, még fizetendő összeg (ajánlati ár − befizetett előleg).
            $table->unsignedInteger('final_amount_huf')->nullable()->after('completed_at');
            $table->timestamp('final_paid_at')->nullable()->after('final_amount_huf');
            $table->string('final_paid_via', 16)->nullable()->after('final_paid_at');
            $table->string('final_checkout_session_id', 255)->nullable()->after('final_paid_via');
            $table->text('final_checkout_url')->nullable()->after('final_checkout_session_id');
            $table->timestamp('final_checkout_expires_at')->nullable()->after('final_checkout_url');
            $table->timestamp('handed_over_at')->nullable()->after('final_checkout_expires_at');
            $table->text('handover_note')->nullable()->after('handed_over_at');
        });

        Schema::table('project_documents', function (Blueprint $table) {
            // Átadandó kód / csomag: az ügyfél csak az átadás után töltheti le.
            $table->boolean('is_deliverable')->default(false)->after('size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('project_documents', fn (Blueprint $table) => $table->dropColumn('is_deliverable'));
        Schema::table('quote_requests', fn (Blueprint $table) => $table->dropColumn([
            'completed_at', 'final_amount_huf', 'final_paid_at', 'final_paid_via', 'final_checkout_session_id',
            'final_checkout_url', 'final_checkout_expires_at', 'handed_over_at', 'handover_note',
        ]));
    }
};
