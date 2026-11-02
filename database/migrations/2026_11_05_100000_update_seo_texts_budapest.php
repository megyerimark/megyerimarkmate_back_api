<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Főoldali cím és leírás: Budapest + online (a frontend core/seo.ts szövegével egyezően).
 * Csak a korábbi alapértelmezett szöveget cseréli – ha saját címet vagy leírást adtál meg, az marad.
 */
return new class extends Migration {
    private const OLD = [
        'meta_title' => 'Weboldal készítés és egyedi webfejlesztés | Megyeri Márk Máté',
        'meta_description' => 'Egyedi weboldal, webshop és webes rendszer készítése vállalkozásoknak – online, itthon és külföldön. Prémium design, gyors betöltés. Kérj ajánlatot!',
    ];
    private const NEW = [
        'meta_title' => 'Weboldal készítés Budapesten és online | Megyeri Márk Máté',
        'meta_description' => 'Egyedi weboldal, landing oldal és webshop vállalkozásoknak – Budapesten személyesen, országosan online. Gyors és mobilbarát. Kérj ajánlatot!',
    ];

    public function up(): void
    {
        foreach (self::OLD as $key => $value) {
            DB::table('site_settings')->where('key', $key)->where('value', $value)->update(['value' => self::NEW[$key]]);
        }
        \App\Support\PublicCache::flush(); // a nyilvános beállítások gyorsítótára azonnal frissüljön
    }

    public function down(): void
    {
        foreach (self::NEW as $key => $value) {
            DB::table('site_settings')->where('key', $key)->where('value', $value)->update(['value' => self::OLD[$key]]);
        }
        \App\Support\PublicCache::flush();
    }
};
