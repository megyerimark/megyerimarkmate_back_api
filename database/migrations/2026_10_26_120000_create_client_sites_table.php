<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Elkészült, élesített projektek nyilvántartása (domain, tárhely, lejáratok).
        Schema::create('client_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('domain', 190)->nullable();
            $table->foreignId('quote_request_id')->nullable()->constrained('quote_requests')->nullOnDelete();
            $table->string('client_name', 120)->nullable();
            $table->string('client_email', 254)->nullable();
            $table->date('launched_on')->nullable();
            $table->string('hosting_provider', 120)->nullable();
            $table->string('domain_registrar', 120)->nullable();
            $table->date('domain_expires_on')->nullable();
            $table->date('hosting_expires_on')->nullable();
            $table->date('ssl_expires_on')->nullable();
            $table->string('tech_stack', 255)->nullable();
            $table->text('notes')->nullable();
            // A böngészőben, a széfjelszóval titkosított hozzáférési adatok (AES-GCM). A szerver nem tudja visszafejteni.
            $table->longText('vault_blob')->nullable();
            $table->timestamps();
            $table->index('domain_expires_on');
            $table->index('hosting_expires_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_sites');
    }
};
