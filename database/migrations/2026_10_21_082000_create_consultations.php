<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('consultation_slots', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at')->unique(); // UTC; clients see their local timezone
            $table->unsignedTinyInteger('duration_minutes')->default(30);
            $table->timestamps();
        });
        Schema::create('consultation_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_slot_id')->unique()->constrained('consultation_slots')->cascadeOnDelete();
            $table->string('customer_name', 120);
            $table->string('email', 254);
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('privacy_notice_acknowledged_at');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('consultation_bookings');
        Schema::dropIfExists('consultation_slots');
    }
};
