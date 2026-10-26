<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Opcionális angol változat a nyelvválasztóhoz; üresen az angol nézet a magyar szöveget mutatja.
        Schema::table('services', function (Blueprint $table) {
            $table->string('title_en', 150)->nullable()->after('title');
            $table->string('summary_en', 300)->nullable()->after('summary');
            $table->text('description_en')->nullable()->after('description');
            $table->json('features_en')->nullable()->after('features');
        });
        Schema::table('portfolio_projects', function (Blueprint $table) {
            $table->string('title_en', 160)->nullable()->after('title');
            $table->string('category_en', 80)->nullable()->after('category');
            $table->string('summary_en', 400)->nullable()->after('summary');
            $table->text('description_en')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('services', fn (Blueprint $t) => $t->dropColumn(['title_en', 'summary_en', 'description_en', 'features_en']));
        Schema::table('portfolio_projects', fn (Blueprint $t) => $t->dropColumn(['title_en', 'category_en', 'summary_en', 'description_en']));
    }
};
