<?php

namespace App\Support;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Email;

/**
 * Szöveges (text/plain) változat minden kimenő levélhez a HTML-ből.
 * A csak HTML-ből álló levelet a spamszűrők (Gmail, Outlook) gyanúsabbnak pontozzák; a szöveges rész
 * ráadásul ott is olvasható, ahol a HTML nem jelenik meg (okosóra, egyes levelezők, képernyőolvasók).
 */
class MailPlainText
{
    public static function handle(MessageSending $event): void
    {
        $message = $event->message;
        if (! $message instanceof Email || $message->getTextBody() !== null) return;
        $html = $message->getHtmlBody();
        if (! is_string($html) || trim($html) === '') return;
        $message->text(self::fromHtml($html), 'utf-8');
    }

    public static function fromHtml(string $html): string
    {
        // fej, stílus, rejtett előnézeti szöveg és képek nélkül
        $html = preg_replace('~<(head|style|script|title)\b.*?</\1>~is', '', $html) ?? $html;
        $html = preg_replace('~<div[^>]*display:\s*none[^>]*>.*?</div>~is', '', $html) ?? $html;
        $html = preg_replace('~<img\b[^>]*>~i', '', $html) ?? $html;
        // linkek: „szöveg (cím)” – a mailto/tel és az azonos szövegű linkek cím nélkül
        $html = preg_replace_callback('~<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>~is', function (array $m) {
            $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:') || $url === $text || $text === '') return $text !== '' ? $text : $url;
            return $text.' ('.$url.')';
        }, $html) ?? $html;
        // blokkszintű elemek: sortörés
        $html = preg_replace('~<br\s*/?>~i', "\n", $html) ?? $html;
        $html = preg_replace('~</(p|div|tr|h[1-6]|li|table)>~i', "\n", $html) ?? $html;
        $html = preg_replace('~</td>~i', '  ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $lines = array_map(fn ($line) => trim(preg_replace('/[ \t]+/', ' ', $line) ?? $line), explode("\n", $text));
        $text = implode("\n", $lines);
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text)."\n";
    }
}
