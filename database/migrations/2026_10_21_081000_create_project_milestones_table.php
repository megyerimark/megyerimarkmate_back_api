<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->constrained('quote_requests')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('sort_order')->default(0);
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->index(['quote_request_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_milestones');
    }
};
