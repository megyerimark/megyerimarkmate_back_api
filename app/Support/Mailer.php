<?php

namespace App\Support;

/** Egy helyen dől el, hogy küldhetünk-e valódi e-mailt (be van kapcsolva, és nem log/array/null a mailer). */
class Mailer
{
    public static function canSend(): bool
    {
        return (bool) config('portfolio.mail_enabled')
            && ! in_array(config('mail.default'), ['log', 'array', 'null'], true);
    }

    /**
     * Rövid, naplózható hibaleírás a sikertelen levélküldésről (pl. SMTP-elutasítás oka), hogy a laravel.log-ból
     * kiderüljön, miért nem ment ki egy levél. Az e-mail-címeket kitakarja, és legfeljebb 300 karakter.
     */
    public static function describe(\Throwable $e): string
    {
        $message = preg_replace('/[^\s<>"\']+@[^\s<>"\']+/', '[email]', $e->getMessage()) ?? '';
        return mb_substr(trim($message), 0, 300);
    }
}
