<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Vékony Brevo API (v3) kliens – csak a kontaktkezeléshez szükséges hívások.
 * Minden hiba RuntimeException, a hívó dönti el, mi legyen vele (naplózás / újrapróbálás).
 */
class Brevo
{
    private const BASE = 'https://api.brevo.com/v3';

    public static function configured(): bool
    {
        return (string) config('services.brevo.key') !== '' && self::listId() > 0;
    }

    public static function listId(): int
    {
        return (int) config('services.brevo.list_id');
    }

    public static function welcomeListId(): int
    {
        return (int) config('services.brevo.welcome_list_id');
    }

    private static function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE)->acceptJson()->asJson()->timeout(15)
            ->withHeaders(['api-key' => (string) config('services.brevo.key')]);
    }

    /**
     * Kontakt létrehozása vagy frissítése, és a megadott listákra tétele.
     * $unblock: a cím a weboldalon most adott (újra) kettős megerősítéssel hozzájárulást – csak ekkor
     * oldjuk fel a Brevóban egy korábbi leiratkozás tiltását. Máskor a leiratkozott állapot érintetlen marad.
     */
    public static function upsertContact(string $email, array $listIds, array $attributes = [], bool $unblock = false): void
    {
        $body = ['email' => $email, 'listIds' => array_values(array_unique(array_filter($listIds))), 'updateEnabled' => true];
        if ($attributes) $body['attributes'] = $attributes;
        if ($unblock) $body['emailBlacklisted'] = false;
        self::check(self::client()->post('/contacts', $body), 'kontakt mentése');
    }

    /** Leiratkozás: a cím tiltólistára kerül a Brevóban (a Brevo nem küld rá több marketinglevelet). */
    public static function blockContact(string $email): void
    {
        $response = self::client()->put('/contacts/'.rawurlencode($email).'?identifierType=email_id', ['emailBlacklisted' => true]);
        if ($response->status() === 404) return; // nincs a Brevóban – nincs teendő
        self::check($response, 'leiratkozás');
    }

    /** Végleges törlés (GDPR törlési kérelem). */
    public static function deleteContact(string $email): void
    {
        $response = self::client()->delete('/contacts/'.rawurlencode($email).'?identifierType=email_id');
        if ($response->status() === 404) return;
        self::check($response, 'törlés');
    }

    /**
     * A Brevóban leiratkozott (tiltott vagy a listáról leiratkozott) címek.
     * $since: csak az azóta módosult kontaktok; null = a teljes kontaktállomány.
     * @return list<string> kisbetűs e-mail-címek
     */
    public static function unsubscribedEmails(?\DateTimeInterface $since = null): array
    {
        $emails = [];
        $limit = 500;
        for ($page = 0, $offset = 0; $page < 100; $page++, $offset += $limit) {
            $query = ['limit' => $limit, 'offset' => $offset, 'sort' => 'asc'];
            if ($since) $query['modifiedSince'] = \Illuminate\Support\Carbon::instance($since)->utc()->format('Y-m-d\TH:i:s.v\Z');
            $response = self::client()->get('/contacts', $query);
            self::check($response, 'kontaktok lekérése');
            $contacts = (array) $response->json('contacts', []);
            foreach ($contacts as $contact) {
                $email = strtolower(trim((string) ($contact['email'] ?? '')));
                $listOff = in_array(self::listId(), array_map('intval', (array) ($contact['listUnsubscribed'] ?? [])), true);
                if ($email !== '' && (! empty($contact['emailBlacklisted']) || $listOff)) $emails[] = $email;
            }
            if (count($contacts) < $limit) break;
        }
        return array_values(array_unique($emails));
    }

    private static function check(Response $response, string $action): void
    {
        if ($response->successful()) return;
        $message = (string) ($response->json('message') ?? '');
        throw new RuntimeException('Brevo hiba ('.$action.'): HTTP '.$response->status().($message !== '' ? ' – '.mb_substr($message, 0, 200) : ''));
    }
}
