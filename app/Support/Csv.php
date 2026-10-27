<?php

namespace App\Support;

/** CSV-export segéd: Excel képlet-injektálás (CSV injection) elleni védelem. */
class Csv
{
    /** A =, +, -, @, tab, CR kezdetű cellák elé aposztróf kerül, így az Excel szövegként kezeli őket. */
    public static function safe(mixed $value): string
    {
        $text = (string) ($value ?? '');
        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
    }
}
