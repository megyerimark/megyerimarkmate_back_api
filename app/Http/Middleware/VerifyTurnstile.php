<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bot-védelem (Cloudflare Turnstile) a nyilvános űrlapokra és az admin belépésre.
 * A böngésző a widgettől kapott egyszer használatos kulcsot a kérés törzsében küldi (turnstile_token),
 * amit itt a Cloudflare-rel ellenőrzünk. Kulcs nélkül (TURNSTILE_SECRET_KEY üres) a védelem ki van kapcsolva,
 * így a helyi fejlesztés és a tesztek nem függnek a Cloudflare-től.
 */
class VerifyTurnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.turnstile.secret_key');
        if ($secret === '') {
            return $next($request);
        }

        $token = $request->input('turnstile_token');
        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            return $this->reject();
        }

        try {
            $result = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ])->json();
        } catch (\Throwable $e) {
            // Zárt hibakezelés: ha az ellenőrzés nem elérhető, nem engedjük át a kérést.
            Log::warning('Turnstile verification unavailable', ['exception_type' => $e::class]);
            return $this->reject();
        }

        if (! is_array($result) || ($result['success'] ?? false) !== true || ! $this->hostnameMatches($result['hostname'] ?? null)) {
            return $this->reject();
        }

        return $next($request);
    }

    /** A kulcs csak a saját domainünkön kiállított widgetből fogadható el (más oldalról lopott kulcs ellen). */
    private function hostnameMatches(mixed $hostname): bool
    {
        $allowed = array_filter(array_map('trim', explode(',', (string) config('services.turnstile.hostnames'))));
        return $allowed === [] || in_array(strtolower((string) $hostname), array_map('strtolower', $allowed), true);
    }

    private function reject(): Response
    {
        return response()->json([
            'message' => 'Az automatikus robotszűrés nem sikerült. Frissítsd az oldalt, és próbáld újra.',
            'errors' => ['turnstile_token' => ['Az automatikus robotszűrés nem sikerült. Frissítsd az oldalt, és próbáld újra.']],
            'error_code' => 'TURNSTILE_FAILED',
        ], 422);
    }
}
