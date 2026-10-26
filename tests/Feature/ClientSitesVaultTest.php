<?php

namespace Tests\Feature;

use App\Mail\AdminPaymentNotice;
use App\Models\ClientSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClientSitesVaultTest extends TestCase
{
    use RefreshDatabase;

    private function blob(string $tag = 'a'): string
    {
        return json_encode(['v' => 1, 'iv' => base64_encode(str_repeat($tag, 12)), 'ct' => base64_encode('ciphertext-'.$tag)]);
    }

    private function vaultPayload(array $blobs = []): array
    {
        return ['salt' => base64_encode(random_bytes(16)), 'iterations' => 600000, 'verifier' => $this->blob('v'), 'blobs' => $blobs];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();
        $this->actingAs($u);
    }

    public function test_sites_crud_and_only_encrypted_blobs_are_accepted(): void
    {
        $id = $this->postJson('/api/admin/sites', ['name' => 'Ügyfél oldal', 'domain' => 'ugyfel.hu', 'vault_blob' => $this->blob()])
            ->assertCreated()->json('data.id');
        // Titkosítatlan jelszó nem kerülhet a széfmezőbe.
        $this->patchJson('/api/admin/sites/'.$id, ['vault_blob' => '{"password":"titok123"}'])->assertUnprocessable();
        $this->patchJson('/api/admin/sites/'.$id, ['domain' => 'nem domain'])->assertUnprocessable();
        $this->patchJson('/api/admin/sites/'.$id, ['notes' => 'Élesítve.'])->assertOk();
        $this->getJson('/api/admin/sites')->assertJsonPath('data.0.domain', 'ugyfel.hu');
        $this->deleteJson('/api/admin/sites/'.$id)->assertOk();
        $this->assertDatabaseCount('client_sites', 0);
    }

    public function test_vault_password_change_must_reencrypt_every_blob(): void
    {
        $this->putJson('/api/admin/vault', $this->vaultPayload())->assertOk();
        $a = ClientSite::create(['name' => 'A']); $a->vault_blob = $this->blob('a'); $a->save();
        $b = ClientSite::create(['name' => 'B']); $b->vault_blob = $this->blob('b'); $b->save();

        $this->putJson('/api/admin/vault', $this->vaultPayload([['id' => $a->id, 'vault_blob' => $this->blob('x')]]))->assertUnprocessable();
        $this->putJson('/api/admin/vault', $this->vaultPayload([
            ['id' => $a->id, 'vault_blob' => $this->blob('x')], ['id' => $b->id, 'vault_blob' => $this->blob('y')],
        ]))->assertOk();
        $this->assertSame($this->blob('y'), $b->fresh()->vault_blob);
        $this->getJson('/api/admin/vault')->assertJsonPath('data.iterations', 600000)->assertJsonMissingPath('data.password');
    }

    public function test_weak_kdf_settings_are_rejected(): void
    {
        $this->putJson('/api/admin/vault', array_merge($this->vaultPayload(), ['iterations' => 1000]))->assertUnprocessable();
    }

    public function test_vault_and_sites_require_admin(): void
    {
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/vault')->assertUnauthorized();
        $this->getJson('/api/admin/sites')->assertUnauthorized();
    }

    public function test_expiry_reminders_and_dashboard(): void
    {
        Mail::fake();
        config()->set(['portfolio.mail_enabled' => true, 'mail.default' => 'smtp', 'portfolio.admin_email' => 'owner@example.com']);
        ClientSite::create(['name' => 'Lejáró', 'domain' => 'lejaro.hu', 'domain_expires_on' => today()->addDays(7)->toDateString()]);
        ClientSite::create(['name' => 'Ráér', 'domain' => 'raer.hu', 'domain_expires_on' => today()->addDays(200)->toDateString()]);
        $this->artisan('portfolio:expiry-reminders')->assertSuccessful();
        Mail::assertSent(AdminPaymentNotice::class, fn ($m) => str_contains(implode(' ', $m->lines), 'lejaro.hu') && ! str_contains(implode(' ', $m->lines), 'raer.hu'));
        $this->getJson('/api/admin/dashboard')->assertJsonCount(1, 'data.sites_expiring');
    }
}
