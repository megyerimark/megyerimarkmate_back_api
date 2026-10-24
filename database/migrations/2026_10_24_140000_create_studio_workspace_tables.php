<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('is_done')->default(false);
            $table->foreignId('quote_request_id')->nullable()->constrained('quote_requests')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_done', 'due_date']);
        });

        Schema::create('studio_client_notes', function (Blueprint $table) {
            $table->id();
            $table->string('client_email', 254);
            $table->text('body');
            $table->timestamps();
            $table->index(['client_email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_client_notes');
        Schema::dropIfExists('studio_tasks');
    }
};
