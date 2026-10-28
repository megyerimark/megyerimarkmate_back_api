<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akciós ajánlatnál az akció előtti (eredeti) ár. Az ajánlatban és a projektoldalon az eredeti és az akciós ár
 * is látszik, így az ügyfél ellenőrizheti, hogy a beküldéskor rögzített kedvezményt megkapta.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->unsignedInteger('list_price_huf')->nullable()->after('quoted_price_huf');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', fn (Blueprint $table) => $table->dropColumn('list_price_huf'));
    }
};
