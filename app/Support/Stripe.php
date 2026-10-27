<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimális Stripe-kliens a hivatalos SDK nélkül (Stripe REST API, form-encoded).
 * Csak azt tudja, amire a portfóliónak szüksége van: Checkout, Billing Portal, előfizetés-lemondás,
 * valamint a webhook-aláírás ellenőrzése.
 *
 * HUF: a Stripe az összeget két tizedesjeggyel várja (1 Ft = 100), lásd docs.stripe.com/currencies.
 */
class Stripe
{
    public const API = 'https://api.stripe.com/v1/';

    /** Csak teszteléshez (pl. stripe-mock); élesben mindig az alapértelmezett Stripe API. */
    private static function base(): string { return rtrim((string) (config('services.stripe.api_base') ?: self::API), '/').'/'; }

    public static function enabled(): bool
    {
        return (bool) config('services.stripe.secret');
    }

    /** Forint → Stripe minor unit. */
    public static function huf(int $forint): int
    {
        return $forint * 100;
    }

    /**
     * @param string|null $idempotencyKey Azonos kulccsal a Stripe 24 órán át ugyanazt a választ adja
     *                                    (párhuzamos/dupla kérés nem hoz létre két objektumot).
     */
    public static function post(string $path, array $params = [], ?string $idempotencyKey = null): array
    {
        return self::request('post', $path, $params, $idempotencyKey);
    }

    public static function get(string $path, array $params = []): array
    {
        return self::request('get', $path, $params);
    }

    public static function delete(string $path, array $params = []): array
    {
        return self::request('delete', $path, $params);
    }

    private static function request(string $method, string $path, array $params, ?string $idempotencyKey = null): array
    {
        if (! self::enabled()) {
            throw new RuntimeException('Stripe is not configured.');
        }
        $client = Http::withToken(config('services.stripe.secret'))->asForm()->timeout(20);
        if ($version = config('services.stripe.api_version')) {
            $client = $client->withHeaders(['Stripe-Version' => $version]);
        }
        if ($idempotencyKey !== null && $method === 'post') {
            $client = $client->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }
        $response = $method === 'get'
            ? $client->get(self::base().$path, $params)
            : $client->{$method}(self::base().$path, $params);
        if ($response->failed()) {
            // A Stripe hibaüzenete nem tartalmaz kártyaadatot; a kulcsot sosem naplózzuk.
            $message = 'Stripe API error ('.$response->status().'): '.($response->json('error.message') ?? 'unknown');
            // A Rendszerállapot panel megmutatja, így nem kell a laravel.log-ban keresgélni.
            Cache::put('portfolio:stripe-last-error', [
                'at' => now()->toDateTimeString(), 'endpoint' => strtok($path, '/'), 'status' => $response->status(),
                'message' => mb_substr((string) ($response->json('error.message') ?? 'unknown'), 0, 300),
                'param' => $response->json('error.param'),
            ], now()->addDays(14));
            throw new RuntimeException($message);
        }
        return $response->json();
    }

    /**
     * Webhook-aláírás ellenőrzése (Stripe-Signature: t=...,v1=...). Visszajátszás ellen 5 perces tűrés.
     * @see https://docs.stripe.com/webhooks#verify-manually
     */
    public static function verifySignature(string $payload, ?string $header, int $tolerance = 300): bool
    {
        $secret = (string) config('services.stripe.webhook_secret');
        if ($secret === '' || ! $header) {
            return false;
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't') $timestamp = (int) $value;
            if ($key === 'v1' && $value) $signatures[] = $value;
        }
        if (! $timestamp || ! $signatures || abs(time() - $timestamp) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) return true;
        }
        return false;
    }
}
