<?php

namespace App\Support;

use App\Models\ConsultationBooking;
use Carbon\CarbonInterface;

/**
 * A konzultációs foglalás naptárbejegyzése: .ics melléklet (Apple, Outlook, Google is beolvassa)
 * és egy kattintásos „Google Naptárhoz adás” link.
 */
class ConsultationCalendar
{
    public static function title(): string
    {
        return 'Konzultáció – Megyeri Márk Máté';
    }

    public static function description(ConsultationBooking $booking): string
    {
        return 'Konzultációs időpont (#'.$booking->id.'). A találkozó módját (online vagy telefon) e-mailben egyeztetjük. '
            .'Módosítás vagy lemondás: válaszolj a visszaigazoló e-mailre.';
    }

    public static function start(ConsultationBooking $booking): CarbonInterface
    {
        return $booking->slot->starts_at->copy()->utc();
    }

    public static function end(ConsultationBooking $booking): CarbonInterface
    {
        return self::start($booking)->addMinutes(max(1, (int) $booking->slot->duration_minutes));
    }

    public static function ics(ConsultationBooking $booking): string
    {
        $fmt = fn (CarbonInterface $t) => $t->copy()->utc()->format('Ymd\THis\Z');
        $host = parse_url((string) config('portfolio.frontend_url'), PHP_URL_HOST) ?: 'megyerimarkmate.hu';
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Megyeri Mark Mate//Konzultacio//HU',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:konzultacio-'.$booking->id.'@'.$host, // ugyanaz, mint a weboldal .ics-gombjáé: nem lesz duplikált bejegyzés
            'DTSTAMP:'.$fmt(now()),
            'DTSTART:'.$fmt(self::start($booking)),
            'DTEND:'.$fmt(self::end($booking)),
            'SUMMARY:'.self::escape(self::title()),
            'DESCRIPTION:'.self::escape(self::description($booking)),
            'STATUS:CONFIRMED',
            'BEGIN:VALARM',
            'TRIGGER:-PT30M',
            'ACTION:DISPLAY',
            'DESCRIPTION:'.self::escape(self::title()),
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    public static function googleUrl(ConsultationBooking $booking): string
    {
        $fmt = fn (CarbonInterface $t) => $t->format('Ymd\THis\Z');
        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => self::title(),
            'dates' => $fmt(self::start($booking)).'/'.$fmt(self::end($booking)),
            'details' => self::description($booking),
        ]);
    }

    private static function escape(string $value): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\;', '\,', '\n', '\n'], $value);
    }

    /** RFC 5545: a 75 bájtnál hosszabb sorokat tördelni kell (UTF-8 karakterhatáron). */
    private static function fold(string $line): string
    {
        $out = '';
        $max = 75; // az első sor 75 bájt, a folytatósorok a kezdő szóközzel együtt
        while (strlen($line) > $max) {
            $cut = $max;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) $cut--;
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
            $max = 74;
        }
        return $out.$line;
    }
}
