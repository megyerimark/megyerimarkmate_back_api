<?php

namespace App\Support;

use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A weboldal hírlevél-feliratkozói ↔ Brevo összehangolása. A weboldal adatbázisa az elsődleges forrás:
 *  - megerősítés után a cím felkerül a Brevo fő listájára (és ha új, az üdvözlő listára is – arra indul a sorozat);
 *  - leiratkozás (bármelyik oldalon) mindkét helyen leiratkozás marad, automatikusan senki nem kerül vissza;
 *  - sikertelen hívás után a sor „szinkronra vár” marad, az ütemezett futás újrapróbálja.
 */
class NewsletterSync
{
    private const LAST_PULL = 'brevo:last_pull_at';
    /** Ennél régebbi megerősítés után már nem indítjuk el az üdvözlő sorozatot (pl. ha a lista később lett beállítva). */
    private const WELCOME_WINDOW_DAYS = 14;

    /** Egy feliratkozó aktuális állapotának feltöltése. Hibánál false, a hiba a sorban látszik. */
    public static function push(NewsletterSubscriber $subscriber): bool
    {
        if (! Brevo::configured() || $subscriber->status === 'pending') return false;
        try {
            if ($subscriber->status === 'unsubscribed') {
                Brevo::blockContact($subscriber->email);
            } else {
                self::pushConfirmed($subscriber);
            }
            $subscriber->forceFill(['brevo_synced_at' => now(), 'brevo_error' => null])->save();
            return true;
        } catch (Throwable $e) {
            $subscriber->forceFill(['brevo_error' => mb_substr($e->getMessage(), 0, 300)])->save();
            Log::warning('Brevo sync failed', ['subscriber_id' => $subscriber->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private static function pushConfirmed(NewsletterSubscriber $subscriber): void
    {
        $lists = [Brevo::listId()];
        $welcome = $subscriber->welcome_status === 'queued';
        if ($welcome && $subscriber->confirmed_at?->lt(now()->subDays(self::WELCOME_WINDOW_DAYS))) {
            $subscriber->welcome_status = 'skipped';
            $welcome = false;
        }
        if ($welcome && Brevo::welcomeListId() > 0) {
            $lists[] = Brevo::welcomeListId();
        }
        $attribute = trim((string) config('services.brevo.lang_attribute'));
        $attributes = $attribute !== '' ? [strtoupper($attribute) => $subscriber->lang ?: 'hu'] : [];
        // Tiltás feloldása csak közvetlenül egy weboldalon történt (újra)megerősítés után – importnál,
        // kézi szinkronnál vagy a régi feliratkozók első feltöltésénél soha.
        Brevo::upsertContact($subscriber->email, $lists, $attributes, unblock: (bool) $subscriber->brevo_unblock);
        $subscriber->brevo_unblock = false;
        if (in_array(Brevo::welcomeListId(), $lists, true) && Brevo::welcomeListId() > 0) {
            $subscriber->welcome_status = 'added';
        }
    }

    /** A szinkronra váró sorok feltöltése. @return array{synced:int, failed:int} */
    public static function pushPending(int $limit = 300): array
    {
        $synced = 0; $failed = 0;
        if (! Brevo::configured()) return compact('synced', 'failed');
        NewsletterSubscriber::whereIn('status', ['confirmed', 'unsubscribed'])->whereNull('brevo_synced_at')
            ->orderBy('id')->limit($limit)->get()
            ->each(function (NewsletterSubscriber $s) use (&$synced, &$failed) { self::push($s) ? $synced++ : $failed++; });
        return compact('synced', 'failed');
    }

    /**
     * A Brevóban (pl. egy Brevo-kampány leiratkozó linkjével) leiratkozottak átvezetése ide.
     * Csak a már feltöltött sorokat érinti: egy friss, még fel nem töltött megerősítést nem írunk felül.
     */
    public static function pullUnsubscribes(bool $full = false): int
    {
        if (! Brevo::configured()) return 0;
        $startedAt = now();
        $last = Cache::get(self::LAST_PULL);
        $since = ! $full && $last ? \Illuminate\Support\Carbon::parse($last)->subMinutes(10) : null;
        $emails = Brevo::unsubscribedEmails($since);
        $changed = 0;
        foreach (array_chunk($emails, 500) as $chunk) {
            $changed += NewsletterSubscriber::whereIn('email', $chunk)->where('status', 'confirmed')->whereNotNull('brevo_synced_at')
                ->update(['status' => 'unsubscribed', 'unsubscribed_at' => now(), 'brevo_synced_at' => now(), 'brevo_error' => null, 'updated_at' => now()]);
        }
        Cache::forever(self::LAST_PULL, $startedAt->toIso8601String());
        return $changed;
    }

    public static function lastPullAt(): ?string
    {
        return Cache::get(self::LAST_PULL);
    }

    /** Teljes kör: feltöltés, majd a leiratkozások lehúzása. Hibánál kivételt dob (a feltöltés eredménye megmarad). */
    public static function run(bool $full = false): array
    {
        $pushed = self::pushPending();
        $unsubscribed = self::pullUnsubscribes($full);
        return $pushed + ['unsubscribed' => $unsubscribed];
    }

    /** Válasz után, a kérést nem lassítva. */
    public static function pushLater(NewsletterSubscriber $subscriber): void
    {
        if (! Brevo::configured()) return;
        $id = $subscriber->id;
        \Illuminate\Support\defer(function () use ($id) {
            if ($fresh = NewsletterSubscriber::find($id)) self::push($fresh);
        });
    }

    public static function forgetLater(string $email): void
    {
        if (! Brevo::configured()) return;
        \Illuminate\Support\defer(function () use ($email) {
            try {
                Brevo::deleteContact($email);
            } catch (Throwable $e) {
                Log::error('Brevo delete failed – töröld kézzel a Brevóban is', ['error' => $e->getMessage()]);
            }
        });
    }
}
