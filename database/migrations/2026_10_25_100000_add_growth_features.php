
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ügyfélvélemények: csak az admin által publikáltak kerülnek a nyilvános oldalra.
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author_name', 120);
            $table->string('author_role', 120)->nullable();
            $table->string('company', 150)->nullable();
            $table->text('quote');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->foreignId('portfolio_project_id')->nullable()
                ->constrained('portfolio_projects')->nullOnDelete();
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_published', 'sort_order']);
        });

        // Sütik és IP-cím nélküli, aggregált eseményszámlálás.
        Schema::create('site_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 32);
            $table->string('path', 160)->nullable();
            $table->string('source', 80)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['event', 'created_at']);
        });

        Schema::table('quote_requests', function (Blueprint $table) {
            // Érdeklődők forrásának követése.
            $table->string('source', 40)->nullable();
            $table->string('utm_source', 80)->nullable();
            $table->string('utm_medium', 80)->nullable();
            $table->string('utm_campaign', 80)->nullable();
            $table->string('referrer_host', 120)->nullable();
            $table->string('landing_path', 160)->nullable();

            // Reakcióidő-mérés és utánkövetés.
            $table->timestamp('first_response_at')->nullable();
            $table->date('follow_up_at')->nullable();

            $table->index('follow_up_at');
        });

        Schema::table('portfolio_projects', function (Blueprint $table) {
            $table->json('results')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_projects', function (Blueprint $table) {
            $table->dropColumn('results');
        });

        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropIndex(['follow_up_at']);

            $table->dropColumn([
                'source',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'referrer_host',
                'landing_path',
                'first_response_at',
                'follow_up_at',
            ]);
        });

        Schema::dropIfExists('site_events');
        Schema::dropIfExists('testimonials');
    }
};
