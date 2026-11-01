<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kiküldött hírlevelek naplója (mit, kinek, mikor, hány címre ment ki). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 150);
            $table->text('body');
            $table->string('button_text', 60)->nullable();
            $table->string('button_url', 500)->nullable();
            $table->string('audience', 5)->default('all'); // all | hu | en
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
    }
};
