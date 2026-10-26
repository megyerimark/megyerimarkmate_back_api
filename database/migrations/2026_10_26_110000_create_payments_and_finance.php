<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_agreements', function (Blueprint $table) {
            // Az admin engedélyezi, hogy az ügyfél a portálon elindítsa a havi kártyás előfizetést.
            $table->boolean('billing_enabled')->default(false)->after('internal_notes');
            $table->string('stripe_customer_id', 64)->nullable()->after('billing_enabled');
            $table->string('stripe_subscription_id', 64)->nullable()->unique()->after('stripe_customer_id');
            $table->string('stripe_status', 32)->nullable()->after('stripe_subscription_id');
            $table->timestamp('current_period_end')->nullable()->after('stripe_status');
            $table->timestamp('last_payment_at')->nullable()->after('current_period_end');
        });

        Schema::table('quote_requests', function (Blueprint $table) {
            // Előleg: az elfogadott ajánlati ár ennyi %-át a munka megkezdése előtt kell kifizetni.
            $table->unsignedTinyInteger('deposit_percent')->nullable()->after('quoted_price_huf');
            $table->timestamp('deposit_paid_at')->nullable()->after('deposit_percent');
            $table->string('deposit_paid_via', 16)->nullable()->after('deposit_paid_at');
            // Szerződéskötés bizonyítéka: ajánlat-ujjlenyomat, ÁSZF-verzió, időpont.
            $table->string('terms_version', 20)->nullable()->after('proposal_accepted_at');
            $table->json('acceptance_evidence')->nullable()->after('terms_version');
        });

        Schema::create('stripe_events', function (Blueprint $table) {
            $table->string('id', 80)->primary();
            $table->string('type', 80);
            $table->timestamp('processed_at');
        });

        Schema::create('finance_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10); // income | expense
            $table->date('occurred_on');
            $table->unsignedBigInteger('amount_huf'); // bruttó (a ténylegesen fizetett/kapott összeg)
            $table->unsignedTinyInteger('vat_rate')->default(0); // 0, 5, 18 vagy 27
            $table->string('category', 60);
            $table->string('description', 255)->nullable();
            $table->foreignId('quote_request_id')->nullable()->constrained('quote_requests')->nullOnDelete();
            $table->string('source', 16)->default('manual'); // manual | stripe
            $table->string('external_ref', 120)->nullable()->unique();
            $table->timestamps();
            $table->index(['type', 'occurred_on']);
        });

        Schema::create('admin_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
        Schema::dropIfExists('finance_entries');
        Schema::dropIfExists('stripe_events');
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['deposit_percent', 'deposit_paid_at', 'deposit_paid_via', 'terms_version', 'acceptance_evidence']);
        });
        Schema::table('maintenance_agreements', function (Blueprint $table) {
            $table->dropUnique(['stripe_subscription_id']);
            $table->dropColumn(['billing_enabled', 'stripe_customer_id', 'stripe_subscription_id', 'stripe_status', 'current_period_end', 'last_payment_at']);
        });
    }
};
