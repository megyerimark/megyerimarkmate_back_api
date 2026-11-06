<?php

namespace Tests\Feature;

use App\Mail\NewsletterConfirmation;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BrevoSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config()->set('portfolio.mail_enabled', true);
        config()->set('mail.default', 'smtp');
        config()->set('portfolio.require_admin_2fa', false);
        config()->set('services.brevo', ['key' => 'xkeysib-test', 'list_id' => 7, 'welcome_list_id' => 8, 'lang_attribute' => 'NYELV']);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();
        return $user;
    }

    private function fakeBrevo(array $contacts = [], int $status = 201): void
    {
        Http::fake([
            'api.brevo.com/v3/contacts?*' => Http::response(['contacts' => $contacts, 'count' => count($contacts)]),
            'api.brevo.com/v3/contacts/*' => Http::response(null, 204),
            'api.brevo.com/v3/contacts' => Http::response(['id' => 1], $status),
        ]);
    }

    /** Feliratkozás + megerősítés a weboldalon; visszaadja a leiratkozó kulcsot. */
    private function subscribeAndConfirm(string $email): string
    {
        $this->postJson('/api/newsletter/subscribe', ['email' => $email, 'consent' => true, 'lang' => 'en'])->assertOk();
        $link = null;
        Mail::assertSent(NewsletterConfirmation::class, function (NewsletterConfirmation $m) use (&$link, $email) {
            if ($m->subscriber->email === $email) $link = $m->confirmUrl;
            return true;
        });
        $token = substr($link, strpos($link, '#') + 1);
        return $this->postJson('/api/newsletter/confirm', ['token' => $token])->assertOk()->json('unsubscribe_token');
    }

    private function contactPosts(): \Illuminate\Support\Collection
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->filter(fn (HttpRequest $r) => $r->method() === 'POST' && $r->url() === 'https://api.brevo.com/v3/contacts')->values();
    }

    public function test_confirmed_subscriber_goes_to_brevo_with_welcome_list_once(): void
    {
        $this->fakeBrevo();
        $unsubscribe = $this->subscribeAndConfirm('anna@example.com');

        $post = $this->contactPosts()->first();
        $this->assertSame('xkeysib-test', $post->header('api-key')[0]);
        $this->assertSame([7, 8], $post['listIds']);
        $this->assertFalse($post['emailBlacklisted']);
        $this->assertSame(['NYELV' => 'en'], $post['attributes']);
        $sub = NewsletterSubscriber::firstOrFail();
        $this->assertSame('added', $sub->welcome_status);
        $this->assertNotNull($sub->brevo_synced_at);

        // Leiratkozás a weboldalon → tiltás a Brevóban is
        $this->postJson('/api/newsletter/unsubscribe', ['token' => $unsubscribe])->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT'
            && str_starts_with($r->url(), 'https://api.brevo.com/v3/contacts/anna%40example.com') && $r['emailBlacklisted'] === true);

        // Újra feliratkozik: a fő listára visszakerül, de az üdvözlő sorozatot nem kapja meg újra
        $this->travel(11)->minutes();
        $this->subscribeAndConfirm('anna@example.com');
        $this->assertSame([7], $this->contactPosts()->last()['listIds']);
    }

    public function test_failed_push_waits_and_scheduled_sync_retries(): void
    {
        $this->fakeBrevo(status: 500);
        $this->subscribeAndConfirm('b@example.com');
        $sub = NewsletterSubscriber::firstOrFail();
        $this->assertNull($sub->brevo_synced_at);
        $this->assertStringContainsString('HTTP 500', $sub->brevo_error);
        $this->assertSame('queued', $sub->welcome_status);

        Http::swap(new \Illuminate\Http\Client\Factory());
        $this->fakeBrevo();
        $this->artisan('portfolio:brevo-sync')->assertSuccessful();
        $sub->refresh();
        $this->assertNotNull($sub->brevo_synced_at);
        $this->assertNull($sub->brevo_error);
        $this->assertSame('added', $sub->welcome_status);
    }

    public function test_brevo_unsubscribes_are_pulled_but_pending_pushes_are_kept(): void
    {
        $synced = NewsletterSubscriber::forceCreate(['email' => 'gone@example.com', 'status' => 'confirmed', 'consent_text' => 'x', 'unsubscribe_token' => str_repeat('a', 64), 'confirmed_at' => now(), 'brevo_synced_at' => now(), 'welcome_status' => 'added']);
        $listOff = NewsletterSubscriber::forceCreate(['email' => 'listoff@example.com', 'status' => 'confirmed', 'consent_text' => 'x', 'unsubscribe_token' => str_repeat('b', 64), 'confirmed_at' => now(), 'brevo_synced_at' => now(), 'welcome_status' => 'added']);
        $fresh = NewsletterSubscriber::forceCreate(['email' => 'back@example.com', 'status' => 'confirmed', 'consent_text' => 'x', 'unsubscribe_token' => str_repeat('c', 64), 'confirmed_at' => now(), 'brevo_synced_at' => null, 'welcome_status' => 'skipped']);
        $this->fakeBrevo([
            ['email' => 'Gone@example.com', 'emailBlacklisted' => true, 'listUnsubscribed' => []],
            ['email' => 'listoff@example.com', 'emailBlacklisted' => false, 'listUnsubscribed' => [7]],
            ['email' => 'stay@example.com', 'emailBlacklisted' => false, 'listUnsubscribed' => [99]],
            // korábban a Brevóban tiltott, de most a weboldalon újra feliratkozott; a feltöltése épp nem sikerül
            ['email' => 'back@example.com', 'emailBlacklisted' => true, 'listUnsubscribed' => []],
        ], status: 500);

        $this->artisan('portfolio:brevo-sync --full');
        $this->assertSame('unsubscribed', $synced->fresh()->status);
        $this->assertSame('unsubscribed', $listOff->fresh()->status);
        $this->assertSame('confirmed', $fresh->fresh()->status);
    }

    public function test_import_requires_consent_skips_unsubscribed_and_never_welcomes_or_unblocks(): void
    {
        $this->fakeBrevo();
        NewsletterSubscriber::forceCreate(['email' => 'left@example.com', 'status' => 'unsubscribed', 'consent_text' => 'x', 'unsubscribe_token' => str_repeat('d', 64), 'unsubscribed_at' => now(), 'brevo_synced_at' => now()]);
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/admin/newsletter/import', ['emails' => 'a@example.com', 'lang' => 'hu', 'confirm' => true])->assertStatus(422);
        $res = $this->actingAs($admin)->postJson('/api/admin/newsletter/import', [
            'emails' => "Uj.Ugyfel@example.com, left@example.com\nnem-email\nuj.ugyfel@example.com;masik@example.com",
            'lang' => 'hu', 'consent_note' => 'Ajánlatkérő űrlapon bejelölt marketing-hozzájárulás', 'confirm' => true,
        ])->assertOk();
        $this->assertSame(['added' => 2, 'already' => 0, 'unsubscribed' => 1, 'invalid' => 1], $res->json('counts'));
        $this->assertSame('unsubscribed', NewsletterSubscriber::where('email', 'left@example.com')->value('status'));

        $posts = $this->contactPosts();
        $this->assertCount(2, $posts);
        foreach ($posts as $post) {
            $this->assertSame([7], $post['listIds']);
            $this->assertArrayNotHasKey('emailBlacklisted', $post->data());
        }
        $this->assertSame(['skipped'], NewsletterSubscriber::where('source', 'import')->pluck('welcome_status')->unique()->values()->all());
    }

    public function test_admin_sync_status_and_gdpr_delete(): void
    {
        $this->fakeBrevo();
        $sub = NewsletterSubscriber::forceCreate(['email' => 'c@example.com', 'status' => 'confirmed', 'consent_text' => 'x', 'unsubscribe_token' => str_repeat('e', 64), 'confirmed_at' => now()->subMonth(), 'welcome_status' => 'queued']);
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/admin/newsletter')->assertOk()
            ->assertJsonPath('brevo.configured', true)->assertJsonPath('brevo.waiting', 1);
        $this->actingAs($admin)->postJson('/api/admin/newsletter/brevo-sync')->assertOk()
            ->assertJsonPath('result.synced', 1)->assertJsonPath('brevo.waiting', 0);
        // egy hónapos megerősítés: már nem indítjuk el az üdvözlő sorozatot
        $this->assertSame('skipped', $sub->fresh()->welcome_status);
        $this->assertSame([7], $this->contactPosts()->first()['listIds']);

        $this->actingAs($admin)->deleteJson('/api/admin/newsletter/'.$sub->id)->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/v3/contacts/c%40example.com'));
    }

    public function test_earlier_subscribers_first_upload_does_not_lift_a_brevo_unsubscribe(): void
    {
        $this->fakeBrevo();
        NewsletterSubscriber::forceCreate(['email' => 'old@example.com', 'status' => 'confirmed', 'source' => 'footer', 'consent_text' => 'x', 'unsubscribe_token' => str_repeat('f', 64), 'confirmed_at' => now()->subYear(), 'welcome_status' => 'skipped']);
        $this->artisan('portfolio:brevo-sync')->assertSuccessful();
        $post = $this->contactPosts()->first();
        $this->assertSame([7], $post['listIds']);
        $this->assertArrayNotHasKey('emailBlacklisted', $post->data());
    }

    public function test_without_brevo_config_nothing_is_called(): void
    {
        config()->set('services.brevo.key', '');
        Http::fake();
        $this->subscribeAndConfirm('d@example.com');
        Http::assertNothingSent();
        $this->artisan('portfolio:brevo-sync')->assertSuccessful();
    }
}
