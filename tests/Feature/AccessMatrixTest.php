<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ellenőrzőlista 6.8 / 11.3 / 11.4 / 11.6: jogosultsági mátrix minden admin-útvonalra
 * (vendég, bejelentkezett nem-admin, admin) és a fájlfeltöltés korlátai.
 */
class AccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();
        return $user;
    }

    /** @return array<int, array{0:string,1:string}> minden /api/admin/* útvonal, paraméterek kitöltve */
    private function adminRoutes(int $quoteId): array
    {
        $out = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/admin')) continue;
            $uri = preg_replace('/\{[^}]+\}/', (string) $quoteId, $route->uri());
            foreach ($route->methods() as $m) if ($m !== 'HEAD') $out[] = [$m, '/'.$uri];
        }
        return $out;
    }

    public function test_every_admin_route_rejects_guests_and_non_admins(): void
    {
        config()->set('portfolio.require_admin_2fa', false);
        $q = QuoteRequest::create(['customer_name' => 'Kiss Anna', 'email' => 'anna@example.com', 'website_type' => 'business', 'project_description' => 'Üzleti weboldal fejlesztése']);
        $routes = $this->adminRoutes($q->id);
        $this->assertGreaterThan(40, count($routes), 'Az admin útvonalak listája gyanúsan rövid.');

        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(401);
        }
        $user = User::factory()->create();
        foreach ($routes as [$method, $uri]) {
            $status = $this->actingAs($user)->json($method, $uri)->status();
            // 403, vagy 404 ha a hivatkozott rekord nem létezik (route-model binding) – soha nem 2xx.
            $this->assertContains($status, [403, 404], "$method $uri nem-adminnak {$status}-t adott");
        }
        // Az admin eléri az olvasó végpontokat (nem 401/403).
        $admin = $this->admin();
        foreach ($routes as [$method, $uri]) {
            if ($method !== 'GET') continue;
            $status = $this->actingAs($admin)->json($method, $uri)->baseResponse->getStatusCode();
            $this->assertNotContains($status, [401, 403], "$method $uri adminnak {$status}");
            $this->assertLessThan(500, $status, "$method $uri szerverhibát adott: {$status}");
        }
    }

    public function test_document_upload_limits_and_private_storage(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config()->set('portfolio.require_admin_2fa', false);
        $q = QuoteRequest::create(['customer_name' => 'Kiss Anna', 'email' => 'anna@example.com', 'website_type' => 'business', 'project_description' => 'Üzleti weboldal fejlesztése']);
        $admin = $this->admin();
        $url = "/api/admin/quote-requests/{$q->id}/documents";

        $this->actingAs($admin)->post($url, ['title' => 'Szkript', 'file' => UploadedFile::fake()->create('x.php', 10, 'application/x-php')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');
        $this->actingAs($admin)->post($url, ['title' => 'Óriás', 'file' => UploadedFile::fake()->create('nagy.pdf', 11000, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');
        $this->actingAs($admin)->post($url, ['title' => 'Szerződés', 'file' => UploadedFile::fake()->create('szerzodes.pdf', 200, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertNotEmpty(Storage::disk('local')->allFiles(), 'A dokumentum nem a privát tárhelyre került.');
        $this->assertEmpty(Storage::disk('public')->allFiles(), 'A dokumentum nyilvánosan elérhető tárhelyre került!');
    }
}
