<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleReviewsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_nothing_happens_without_configuration(): void
    {
        config()->set(['services.google_places.key' => '', 'services.google_places.place_id' => '']);
        Http::fake();
        $this->artisan('portfolio:google-reviews')->assertSuccessful();
        Http::assertNothingSent();
        $this->getJson('/api/google-reviews')->assertOk()->assertJsonPath('data', null);
    }

    public function test_reviews_are_fetched_stored_and_served(): void
    {
        config()->set(['services.google_places.key' => 'test-key', 'services.google_places.place_id' => 'ChIJabcdefghijklmnop']);
        Http::fake(['places.googleapis.com/*' => Http::response([
            'displayName' => ['text' => 'Megyeri Márk Máté – Webfejlesztés'],
            'rating' => 4.94, 'userRatingCount' => 7, 'googleMapsUri' => 'https://maps.google.com/?cid=123',
            'reviews' => [
                ['rating' => 5, 'publishTime' => '2026-11-01T10:00:00Z', 'text' => ['text' => 'Translated'], 'originalText' => ['text' => 'Nagyon gyors és pontos munka!'],
                 'authorAttribution' => ['displayName' => 'Kiss Anna', 'uri' => 'https://www.google.com/maps/contrib/1']],
                ['rating' => 4, 'text' => ['text' => ''], 'authorAttribution' => ['displayName' => 'Csak csillag']],
                ['rating' => 5, 'text' => ['text' => '<script>x</script> Szuper'], 'authorAttribution' => ['displayName' => 'Nagy Béla', 'uri' => 'javascript:alert(1)']],
            ],
        ])]);

        $this->artisan('portfolio:google-reviews')->assertSuccessful();
        Http::assertSent(fn ($r) => $r->hasHeader('X-Goog-Api-Key', 'test-key') && str_contains($r->url(), 'places/ChIJabcdefghijklmnop'));

        $this->getJson('/api/google-reviews')->assertOk()
            ->assertJsonPath('data.rating', 4.9)->assertJsonPath('data.count', 7)
            ->assertJsonCount(2, 'data.reviews') // a szöveg nélküli értékelés kimarad
            ->assertJsonPath('data.reviews.0.text', 'Nagyon gyors és pontos munka!')
            ->assertJsonPath('data.reviews.0.author_url', 'https://www.google.com/maps/contrib/1')
            ->assertJsonPath('data.reviews.1.author_url', null) // nem https link nem kerül ki
            ->assertJsonMissingPath('data.fetched_at');
    }

    public function test_failed_refresh_keeps_the_previous_data(): void
    {
        config()->set(['services.google_places.key' => 'k', 'services.google_places.place_id' => 'ChIJabcdefghijklmnop']);
        Storage::disk('local')->put('google-reviews.json', json_encode(['rating' => 5, 'count' => 3, 'url' => null, 'reviews' => []]));
        Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 400)]);
        $this->artisan('portfolio:google-reviews')->assertFailed();
        $this->getJson('/api/google-reviews')->assertJsonPath('data.count', 3);
    }
}
