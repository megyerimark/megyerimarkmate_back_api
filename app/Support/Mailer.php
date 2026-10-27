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
}
