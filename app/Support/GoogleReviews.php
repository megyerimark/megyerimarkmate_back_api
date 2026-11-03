<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Google-vélemények a saját Google Cégprofilból, a Google hivatalos Places API-jával (új változat).
 * Naponta egyszer frissül (portfolio:google-reviews), az eredmény a storage/app/google-reviews.json fájlba kerül
 * – a gyorsítótár ürítése (deploy) nem törli. A Google ingyenes havi keretébe napi egy lekérés bőven belefér.
 *
 * A Google feltételei szerint a vélemények mellett fel kell tüntetni a forrást (Google) és a szerzőt
 * (névvel, a profiljára mutató linkkel) – a frontend így jeleníti meg.
 */
class GoogleReviews
{
    private const FILE = 'google-reviews.json';
    private const ENDPOINT = 'https://places.googleapis.com/v1/places/';

    public static function configured(): bool
    {
        return (string) config('services.google_places.key') !== '' && (string) config('services.google_places.place_id') !== '';
    }

    /** A legutóbb elmentett adatok (vagy null, ha még nincs). */
    public static function stored(): ?array
    {
        if (! Storage::disk('local')->exists(self::FILE)) return null;
        $data = json_decode((string) Storage::disk('local')->get(self::FILE), true);
        return is_array($data) ? $data : null;
    }

    /** Lekérés a Google-től és mentés; hibánál kivételt dob (a korábbi mentés megmarad). */
    public static function refresh(): array
    {
        $placeId = (string) config('services.google_places.place_id');
        if (! preg_match('/\A[A-Za-z0-9_-]{10,200}\z/', $placeId)) {
            throw new RuntimeException('Érvénytelen GOOGLE_PLACE_ID.');
        }
        $response = Http::timeout(15)->withHeaders([
            'X-Goog-Api-Key' => (string) config('services.google_places.key'),
            'X-Goog-FieldMask' => 'displayName,rating,userRatingCount,googleMapsUri,reviews',
        ])->get(self::ENDPOINT.$placeId, ['languageCode' => 'hu']);
        if (! $response->successful()) {
            throw new RuntimeException('Google Places API hiba: HTTP '.$response->status().' '.mb_substr((string) $response->json('error.message'), 0, 200));
        }
        $data = self::normalize((array) $response->json());
        Storage::disk('local')->put(self::FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        PublicCache::flush();
        return $data;
    }

    /** Csak a megjelenítéshez szükséges, ellenőrzött mezők. */
    public static function normalize(array $place): array
    {
        $reviews = [];
        foreach (array_slice((array) ($place['reviews'] ?? []), 0, 5) as $review) {
            // az eredeti nyelvű szöveg (ha a Google lefordította volna)
            $text = trim((string) ($review['originalText']['text'] ?? $review['text']['text'] ?? ''));
            $author = $review['authorAttribution'] ?? [];
            $authorUrl = (string) ($author['uri'] ?? '');
            $reviews[] = [
                'author' => mb_substr(trim((string) ($author['displayName'] ?? '')), 0, 80) ?: 'Google-felhasználó',
                'author_url' => str_starts_with($authorUrl, 'https://') ? $authorUrl : null,
                'rating' => max(1, min(5, (int) ($review['rating'] ?? 5))),
                'text' => mb_substr($text, 0, 1200),
                'published_at' => isset($review['publishTime']) ? substr((string) $review['publishTime'], 0, 10) : null,
            ];
        }
        $url = (string) ($place['googleMapsUri'] ?? '');
        return [
            'name' => (string) ($place['displayName']['text'] ?? ''),
            'rating' => isset($place['rating']) ? round((float) $place['rating'], 1) : null,
            'count' => (int) ($place['userRatingCount'] ?? 0),
            'url' => str_starts_with($url, 'https://') ? $url : null,
            'reviews' => array_values(array_filter($reviews, fn ($r) => $r['text'] !== '')),
            'fetched_at' => now()->toIso8601String(),
        ];
    }
}
