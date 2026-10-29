<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

/**
 * Keresőbarát főoldali cím és leírás. Csak a korábbi alapértelmezett szöveget cseréli le –
 * ha az adminban saját címet vagy leírást adtál meg, az marad.
 */
return new class extends Migration {
    private const OLD = [
        'meta_title' => 'Megyeri Márk | Prémium webfejlesztés vállalkozásoknak',
        'meta_description' => 'Prémium, egyedi weboldalak vállalkozásoknak. Megyeri Márk webfejlesztés: modern megjelenés, reszponzív kivitelezés és ügyfélszerzést támogató megoldások.',
    ];

    public function up(): void
    {
        $new = ['meta_title' => SiteSetting::META_TITLE, 'meta_description' => SiteSetting::META_DESCRIPTION];
        foreach (self::OLD as $key => $value) {
            SiteSetting::where('key', $key)->where('value', $value)->update(['value' => $new[$key]]);
        }
    }

    public function down(): void
    {
        $new = ['meta_title' => SiteSetting::META_TITLE, 'meta_description' => SiteSetting::META_DESCRIPTION];
        foreach (self::OLD as $key => $value) {
            SiteSetting::where('key', $key)->where('value', $new[$key])->update(['value' => $value]);
        }
    }
};
