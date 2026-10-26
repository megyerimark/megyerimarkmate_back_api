<?php

namespace App\Support;

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

    public static function enabled(): bool
    {
        return (bool) config('services.stripe.secret');
    }

    /** Forint → Stripe minor unit. */
    public static function huf(int $forint): int
    {
        return $forint * 100;
    }

    public static function post(string $path, array $params = []): array
    {
        return self::request('post', $path, $params);
    }

    public static function get(string $path, array $params = []): array
    {
        return self::request('get', $path, $params);
    }

    public static function delete(string $path, array $params = []): array
    {
        return self::request('delete', $path, $params);
    }

    private static function request(string $method, string $path, array $params): array
    {
        if (! self::enabled()) {
            throw new RuntimeException('Stripe is not configured.');
        }
        $client = Http::withToken(config('services.stripe.secret'))->asForm()->timeout(20);
        if ($version = config('services.stripe.api_version')) {
            $client = $client->withHeaders(['Stripe-Version' => $version]);
        }
        $response = $method === 'get'
            ? $client->get(self::API.$path, $params)
            : $client->{$method}(self::API.$path, $params);
        if ($response->failed()) {
            // A Stripe hibaüzenete nem tartalmaz kártyaadatot; a kulcsot sosem naplózzuk.
            throw new RuntimeException('Stripe API error ('.$response->status().'): '.($response->json('error.message') ?? 'unknown'));
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
