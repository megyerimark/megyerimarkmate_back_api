<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name', 120);
            $table->string('email', 254);
            $table->string('phone', 30)->nullable();
            $table->string('company_name', 150)->nullable();
            $table->string('website_type', 60);
            $table->string('design_style', 60)->nullable();
            $table->json('colors')->nullable();
            $table->json('features')->nullable();
            $table->json('inspiration_urls')->nullable();
            $table->string('budget_range', 60)->nullable();
            $table->date('desired_deadline')->nullable();
            $table->longText('project_description');
            $table->boolean('wants_maintenance')->default(false);
            $table->string('status', 30)->default('new');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
