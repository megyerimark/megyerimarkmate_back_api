<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Az ügyfélnek szóló levelek nyelve. A sablonokban a magyar szöveg a kulcs: mt('Kedves :name!', ['name' => …]).
 * Angol levélnél (a Mailable locale-ja 'en') a resources/mail-lang/en.php fordítását adja, egyébként a magyart.
 * Szándékosan nem a Laravel JSON-fordítása: annál a hiányzó magyar kulcs a fallback (angol) nyelvre esne vissza.
 */
class MailText
{
    private static ?array $en = null;

    public static function isEnglish(): bool
    {
        return app()->getLocale() === 'en';
    }

    public static function t(string $hu, array $replace = []): string
    {
        $text = $hu;
        if (self::isEnglish()) {
            self::$en ??= require resource_path('mail-lang/en.php');
            $text = self::$en[$hu] ?? $hu;
        }
        foreach ($replace as $key => $value) {
            $text = str_replace(':'.$key, (string) $value, $text);
        }
        return $text;
    }

    /** Dátum (budapesti idő): 2026. 11. 12. / 12 November 2026 */
    public static function date(?CarbonInterface $value): string
    {
        if (! $value) return '';
        $d = $value->copy()->timezone('Europe/Budapest')->locale(self::isEnglish() ? 'en' : 'hu');
        return self::isEnglish() ? $d->translatedFormat('j F Y') : $d->format('Y. m. d.');
    }

    /** Dátum és idő (budapesti idő): 2026. 11. 12. 10:30 / 12 November 2026, 10:30 */
    public static function dateTime(?CarbonInterface $value): string
    {
        if (! $value) return '';
        $d = $value->copy()->timezone('Europe/Budapest')->locale(self::isEnglish() ? 'en' : 'hu');
        return self::isEnglish() ? $d->translatedFormat('j F Y, H:i') : $d->format('Y. m. d. H:i');
    }

    /** Hosszú dátum a hét napjával: 2026. november 12., csütörtök / Thursday, 12 November 2026 */
    public static function longDate(?CarbonInterface $value): string
    {
        if (! $value) return '';
        $d = $value->copy()->timezone('Europe/Budapest')->locale(self::isEnglish() ? 'en' : 'hu');
        return self::isEnglish() ? $d->translatedFormat('l, j F Y') : $d->translatedFormat('Y. F j., l');
    }

    /** Pénzösszeg: 382 000 Ft / HUF 382,000 */
    public static function money(?int $amount): string
    {
        if ($amount === null) return '';
        return self::isEnglish() ? 'HUF '.number_format($amount, 0, '.', ',') : number_format($amount, 0, ',', ' ').' Ft';
    }

    /**
     * Ügyfélnek küldött frontend-link: angol ügyfélnél ?lang=en, így a privát oldal (portál, foglaláskezelés) angolul nyílik.
     * A titkos token a # utáni részben marad (nem kerül szervernaplóba).
     */
    public static function frontendLink(string $path, ?string $token, ?object $model): string
    {
        $url = rtrim((string) config('portfolio.frontend_url'), '/').$path;
        if (self::langOf($model) === 'en') $url .= '?lang=en';
        return $url.($token !== null && $token !== '' ? '#'.$token : '');
    }

    /** Az ügyfél nyelve egy rekordból (hu alapértelmezéssel). */
    public static function langOf(?object $model): string
    {
        return ($model->lang ?? 'hu') === 'en' ? 'en' : 'hu';
    }
}
