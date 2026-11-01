<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Újrahasznosítható ajánlatsablonok (csomagok): ár, előleg, tartalom, ütemezés, feltételek, érvényesség. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('website_type', 20)->nullable();
            $table->unsignedInteger('price_huf')->nullable();
            $table->unsignedTinyInteger('deposit_percent')->nullable();
            $table->text('summary');
            $table->text('schedule')->nullable();
            $table->text('terms')->nullable();
            $table->unsignedSmallInteger('valid_days')->default(14);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_templates');
    }
};
