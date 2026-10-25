<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->unique()->constrained('quote_requests')->cascadeOnDelete();
            $table->string('plan_name', 100)->default('Aurora Care');
            $table->unsignedInteger('monthly_fee_huf')->default(2000);
            $table->string('status', 16)->default('draft');
            $table->date('starts_on')->nullable();
            $table->date('next_review_on')->nullable();
            $table->text('included_services')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_review_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_agreements');
    }
};
