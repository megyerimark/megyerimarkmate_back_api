<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterConfirmation;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Hírlevél-feliratkozás kettős megerősítéssel (GDPR): a cím csak a megerősítő linkre kattintva lesz aktív.
 * A válasz sosem árulja el, hogy egy cím már fel van-e iratkozva.
 */
class NewsletterController extends Controller
{
    public const CONSENT_TEXT = 'Feliratkozom a Megyeri Márk Máté hírlevelére, és hozzájárulok, hogy az e-mail-címemre akciókról, kedvezményekről és újdonságokról értesítést kapjak. A hozzájárulás bármikor visszavonható a levelekben található leiratkozási linkkel.';

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'consent' => ['accepted'],
            'lang' => ['nullable', 'in:hu,en'],
            'source' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'max:0'], // honeypot: botok kitöltik
        ]);
        $email = Str::lower(trim($data['email']));
        $message = ['message' => 'Köszönöm! Küldtem egy megerősítő e-mailt – a feliratkozás a benne lévő linkre kattintva lesz érvényes.'];

        $subscriber = NewsletterSubscriber::firstOrNew(['email' => $email]);
        if ($subscriber->exists && $subscriber->status === 'confirmed') {
            return response()->json($message); // nem áruljuk el, hogy már feliratkozott
        }
        // Ugyanarra a címre legfeljebb 10 percenként küldünk megerősítő levelet (zaklatás ellen).
        if ($subscriber->confirmation_sent_at && $subscriber->confirmation_sent_at->gt(now()->subMinutes(10))) {
            return response()->json($message);
        }

        $token = bin2hex(random_bytes(32));
        $subscriber->fill([
            'status' => 'pending', 'lang' => $data['lang'] ?? 'hu', 'source' => $data['source'] ?? null,
            'consent_text' => self::CONSENT_TEXT, 'consent_ip' => $request->ip(),
            'consent_user_agent' => Str::limit((string) $request->userAgent(), 290, ''),
        ]);
        $subscriber->confirm_token_hash = hash('sha256', $token);
        $subscriber->unsubscribe_token ??= bin2hex(random_bytes(32));
        $subscriber->confirmation_sent_at = now();
        $subscriber->unsubscribed_at = null;
        $subscriber->save();

        if (\App\Support\Mailer::canSend()) {
            $link = config('portfolio.frontend_url').'/hirlevel/megerosites#'.$token;
            \Illuminate\Support\defer(function () use ($subscriber, $link) {
                try {
                    Mail::to($subscriber->email)->send(new NewsletterConfirmation($subscriber, $link));
                } catch (\Throwable $e) {
                    Log::error('Newsletter confirmation failed', ['subscriber_id' => $subscriber->id, 'exception_type' => $e::class]);
                }
            });
        }

        return response()->json($message);
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/']]);
        $subscriber = NewsletterSubscriber::where('confirm_token_hash', hash('sha256', $data['token']))->first();
        abort_unless($subscriber, 404, 'A megerősítő link érvénytelen vagy már felhasználták.');
        abort_if($subscriber->confirmation_sent_at?->lt(now()->subDays(7)), 410, 'A megerősítő link lejárt – iratkozz fel újra.');

        $subscriber->status = 'confirmed';
        $subscriber->confirmed_at = now();
        $subscriber->confirm_token_hash = null;
        $subscriber->save();

        return response()->json(['message' => 'Sikeres feliratkozás! Elsőként értesítelek az akciókról.', 'unsubscribe_token' => $subscriber->unsubscribe_token]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/']]);
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $data['token'])->first();
        if ($subscriber && $subscriber->status !== 'unsubscribed') {
            $subscriber->status = 'unsubscribed';
            $subscriber->unsubscribed_at = now();
            $subscriber->confirm_token_hash = null;
            $subscriber->save();
        }
        // Érvénytelen kulcsnál is ugyanaz a válasz – ne lehessen vele címeket próbálgatni.
        return response()->json(['message' => 'Leiratkoztál a hírlevélről. Több levelet nem küldök erre a címre.']);
    }
}
