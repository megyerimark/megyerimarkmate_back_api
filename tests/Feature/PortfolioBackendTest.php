<?php

namespace Tests\Feature;

use App\Mail\CustomerQuoteConfirmation;
use App\Mail\NewQuoteNotification;
use App\Mail\ProposalReady;
use App\Models\ProjectMilestone;
use App\Models\ConsultationSlot;
use App\Models\PortfolioProject;
use App\Models\QuoteRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortfolioBackendTest extends TestCase
{
    use RefreshDatabase;

    private function sampleQuote(): array
    {
        return [
            'customer_name' => 'Teszt Ügyfél',
            'email' => 'teszt@example.com',
            'website_type' => 'business',
            'design_style' => 'dark-premium',
            'colors' => ['#7950F2'],
            'features' => ['kapcsolatfelvétel'],
            'project_description' => 'Céges weboldalt szeretnék ajánlatkérővel.',
            'privacy_notice_acknowledged' => true,
        ];
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();
        return $user;
    }

    public function test_an_enquiry_is_saved_and_cannot_set_admin_fields(): void
    {
        config()->set('portfolio.mail_enabled', false);
        $response = $this->postJson('/api/quote-requests', array_merge($this->sampleQuote(), [
            'status' => 'accepted', 'admin_notes' => 'injected',
        ]));
        $response->assertCreated()->assertJsonPath('reference', 1);
        $this->assertDatabaseHas('quote_requests', [
            'email' => 'teszt@example.com', 'status' => 'new', 'admin_notes' => null,
        ]);
        $this->assertNotNull(QuoteRequest::first()->privacy_notice_acknowledged_at);
    }

    public function test_invalid_data_and_spam_are_rejected(): void
    {
        $this->postJson('/api/quote-requests', [
            'customer_name' => 'Bad', 'website_type' => 'invalid',
        ])->assertUnprocessable();
        $this->postJson('/api/quote-requests', array_merge($this->sampleQuote(), [
            'website' => 'spam-link',
        ]))->assertUnprocessable();
        $this->assertDatabaseCount('quote_requests', 0);
    }

    public function test_owner_and_customer_mails_are_sent_when_enabled(): void
    {
        Mail::fake();
        config()->set('portfolio.mail_enabled', true);
        config()->set('mail.default', 'smtp');
        config()->set('portfolio.admin_email', 'owner@example.com');
        config()->set('portfolio.customer_receipt_enabled', true);
        $this->postJson('/api/quote-requests', $this->sampleQuote())->assertCreated();
        Mail::assertSent(NewQuoteNotification::class, 1);
        Mail::assertSent(CustomerQuoteConfirmation::class, 1);
    }

    public function test_admin_routes_are_protected(): void
    {
        $this->getJson('/api/admin/quote-requests')->assertUnauthorized();
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/quote-requests')->assertForbidden();
        $this->actingAs($this->admin())
            ->getJson('/api/admin/quote-requests')->assertOk();
    }

    public function test_only_an_admin_can_log_in(): void
    {
        $guest = User::factory()->create();
        $this->postJson('/admin/login', [
            'email' => $guest->email, 'password' => 'password',
        ])->assertUnauthorized();

        $admin = $this->admin();
        $this->postJson('/admin/login', [
            'email' => $admin->email, 'password' => 'wrong-password',
        ])->assertUnauthorized();
        $this->postJson('/admin/login', [
            'email' => $admin->email, 'password' => 'password',
        ])->assertOk();
        $this->getJson('/api/admin/me')->assertOk();
        $this->postJson('/admin/logout')->assertOk();
        $this->getJson('/api/admin/me')->assertUnauthorized();
    }

    public function test_admin_can_update_and_delete_quote(): void
    {
        $admin = $this->admin();
        $quote = QuoteRequest::create($this->sampleQuoteForModel());
        $this->actingAs($admin)->patchJson('/api/admin/quote-requests/'.$quote->id, [
            'status' => 'reviewing', 'quoted_price_huf' => 199000, 'admin_notes' => 'Felhívni',
        ])->assertOk()->assertJsonPath('data.status', 'reviewing');
        $this->assertDatabaseHas('quote_requests', ['id' => $quote->id, 'quoted_price_huf' => 199000]);
        $this->actingAs($admin)->deleteJson('/api/admin/quote-requests/'.$quote->id)->assertOk();
        $this->assertDatabaseMissing('quote_requests', ['id' => $quote->id]);
    }


    public function test_portal_token_is_private_and_an_unauthorized_user_cannot_see_project_details(): void
    {
        Mail::fake();
        config()->set('portfolio.mail_enabled', true);
        config()->set('mail.default', 'smtp');
        config()->set('portfolio.admin_email', 'owner@example.com');
        config()->set('portfolio.customer_receipt_enabled', true);
        $this->postJson('/api/quote-requests', $this->sampleQuote())->assertCreated()->assertJsonMissing(['portal_token_hash']);
        $token = null;
        Mail::assertSent(CustomerQuoteConfirmation::class, function (CustomerQuoteConfirmation $mail) use (&$token) {
            $token = parse_url($mail->portalUrl, PHP_URL_FRAGMENT);
            return is_string($token) && strlen($token) === 64;
        });
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->postJson('/api/client/project', ['token' => str_repeat('a', 64)])->assertNotFound();
        $this->postJson('/api/client/project', ['token' => $token])->assertOk()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.quoted_price_huf', null)
            ->assertJsonCount(0, 'data.milestones');
        $this->postJson('/api/client/project/accept', ['token' => $token, 'accept_terms' => true])->assertUnprocessable();
    }

    public function test_quoted_status_requires_real_proposal_send_and_acceptance_is_one_time(): void
    {
        Mail::fake();
        config()->set('portfolio.mail_enabled', true);
        config()->set('mail.default', 'smtp');
        config()->set('portfolio.admin_email', 'owner@example.com');
        config()->set('mail.default', 'smtp');
        $quote = QuoteRequest::create($this->sampleQuoteForModel());
        $admin = $this->admin();
        $this->actingAs($admin)->patchJson('/api/admin/quote-requests/'.$quote->id, ['status' => 'quoted'])
            ->assertUnprocessable();
        $this->actingAs($admin)->patchJson('/api/admin/quote-requests/'.$quote->id, [
            'quoted_price_huf' => 325000,
            'proposal_summary' => 'Egyedi céges weboldal, öt aloldal, átadás és két kör módosítás.',
            'proposal_valid_until' => now()->addDays(14)->toDateString(),
        ])->assertOk();
        $this->actingAs($admin)->postJson('/api/admin/quote-requests/'.$quote->id.'/send-proposal')
            ->assertOk()->assertJsonPath('data.status', 'quoted');
        $token = null;
        Mail::assertSent(ProposalReady::class, function (ProposalReady $mail) use (&$token) {
            $token = parse_url($mail->portalUrl, PHP_URL_FRAGMENT);
            return is_string($token) && strlen($token) === 64;
        });
        $this->postJson('/api/client/project', ['token' => $token])
            ->assertOk()->assertJsonPath('data.quoted_price_huf', 325000);
        $this->postJson('/api/client/project/accept', ['token' => $token, 'accept_terms' => false])->assertUnprocessable();
        $this->postJson('/api/client/project/accept', ['token' => $token, 'accept_terms' => true])
            ->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->postJson('/api/client/project/accept', ['token' => $token, 'accept_terms' => true])
            ->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->assertDatabaseHas('quote_requests', ['id' => $quote->id, 'status' => 'accepted']);
        $this->actingAs($admin)->patchJson('/api/admin/quote-requests/'.$quote->id, [
            'status' => 'completed',
        ])->assertOk();
    }

    public function test_fake_mail_transport_does_not_mark_a_proposal_sent(): void
    {
        $quote = QuoteRequest::create($this->sampleQuoteForModel());
        $quote->quoted_price_huf = 250000;
        $quote->proposal_summary = 'Egyedi tervezés és fejlesztés pontos részletekkel.';
        $quote->proposal_valid_until = now()->addDays(10);
        $quote->save();
        config()->set('portfolio.mail_enabled', true);
        config()->set('mail.default', 'log');
        $this->actingAs($this->admin())->postJson('/api/admin/quote-requests/'.$quote->id.'/send-proposal')
            ->assertStatus(503);
        $this->assertNull($quote->fresh()->quoted_at);
    }

    public function test_milestones_are_private_to_their_enquiry_and_visible_only_after_acceptance(): void
    {
        $admin = $this->admin();
        $first = QuoteRequest::create($this->sampleQuoteForModel());
        $second = QuoteRequest::create(array_merge($this->sampleQuoteForModel(), ['email' => 'mas@example.com']));
        $created = $this->actingAs($admin)->postJson('/api/admin/quote-requests/'.$first->id.'/milestones', [
            'title' => 'Arculattervezés', 'status' => 'in_progress',
        ])->assertCreated();
        $id = $created->json('data.id');
        $this->actingAs($admin)->patchJson('/api/admin/quote-requests/'.$second->id.'/milestones/'.$id, [
            'status' => 'done',
        ])->assertNotFound();
        $this->actingAs($admin)->patchJson('/api/admin/quote-requests/'.$first->id.'/milestones/'.$id, [
            'status' => 'done',
        ])->assertOk();
        $this->assertDatabaseHas('project_milestones', ['id' => $id, 'status' => 'done']);
    }


    public function test_consultation_slot_can_be_booked_once_and_admin_sees_customer(): void
    {
        config()->set('portfolio.mail_enabled', false);
        $admin = $this->admin();
        $slot = $this->actingAs($admin)->postJson('/api/admin/consultations', [
            'starts_at' => now()->addDays(3)->toIso8601String(),
            'duration_minutes' => 30,
        ])->assertCreated()->json('data');
        $this->getJson('/api/consultations/available')->assertOk()->assertJsonCount(1, 'data');
        $booking = [
            'consultation_slot_id' => $slot['id'], 'customer_name' => 'Teszt Ügyfél',
            'email' => 'booking@example.com', 'privacy_notice_acknowledged' => true,
        ];
        $this->postJson('/api/consultations/book', $booking)->assertCreated();
        $this->postJson('/api/consultations/book', $booking)->assertUnprocessable();
        $this->getJson('/api/consultations/available')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/consultations')->assertOk()->assertJsonPath('data.0.booking.email', 'booking@example.com');
        $this->deleteJson('/api/admin/consultations/'.$slot['id'])->assertUnprocessable();
    }

    public function test_consultation_slots_are_admin_only_and_honeypot_blocks_spam(): void
    {
        $this->postJson('/api/admin/consultations', [
            'starts_at' => now()->addDays(2)->toIso8601String(), 'duration_minutes' => 30,
        ])->assertUnauthorized();
        $slot = ConsultationSlot::create(['starts_at' => now()->addDays(2), 'duration_minutes' => 45]);
        $this->postJson('/api/consultations/book', [
            'consultation_slot_id' => $slot->id, 'customer_name' => 'Bad Bot',
            'email' => 'bot@example.com', 'website' => 'https://spam.example',
            'privacy_notice_acknowledged' => true,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('consultation_bookings', 0);
    }

    public function test_private_project_messages_and_documents_cannot_be_read_by_another_client(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $admin = $this->admin();
        $quoteA = QuoteRequest::create($this->sampleQuoteForModel());
        $quoteB = QuoteRequest::create(array_merge($this->sampleQuoteForModel(), ['email' => 'masodik@example.com']));
        $tokenA = bin2hex(random_bytes(32));
        $tokenB = bin2hex(random_bytes(32));
        $quoteA->portal_token_hash = hash('sha256', $tokenA);
        $quoteA->portal_token_expires_at = now()->addDays(30);
        $quoteA->save();
        $quoteB->portal_token_hash = hash('sha256', $tokenB);
        $quoteB->portal_token_expires_at = now()->addDays(30);
        $quoteB->save();
        $this->postJson('/api/client/project/messages', ['token' => $tokenA, 'body' => 'Kérdésem van az oldaltervről.'])->assertCreated();
        $this->postJson('/api/client/project/collaboration', ['token' => $tokenB])
            ->assertOk()->assertJsonCount(0, 'data.messages');
        $this->actingAs($admin)->postJson('/api/admin/quote-requests/'.$quoteA->id.'/messages', [
            'body' => 'Elküldöm a friss látványtervet.'
        ])->assertCreated();
        $this->postJson('/api/client/project/collaboration', ['token' => $tokenA])
            ->assertOk()->assertJsonCount(2, 'data.messages');
        $upload = $this->actingAs($admin)->post('/api/admin/quote-requests/'.$quoteA->id.'/documents', [
            'title' => 'Tervezési dokumentum', 'file' => UploadedFile::fake()->create('terv.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $id = $upload->json('data.id');
        $this->assertArrayNotHasKey('storage_path', $upload->json('data'));
        $this->postJson('/api/client/project/documents/'.$id.'/download', ['token' => $tokenB])->assertNotFound();
        $this->post('/api/client/project/documents/'.$id.'/download', ['token' => $tokenA])->assertOk();
        $this->getJson('/api/admin/quote-requests/'.$quoteA->id.'/collaboration')->assertOk();
        $this->actingAs(User::factory()->create())->getJson('/api/admin/quote-requests/'.$quoteA->id.'/collaboration')->assertForbidden();
    }

    public function test_admin_can_never_publish_a_demo_and_cannot_overlap_consultation_slots(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/admin/projects', [
            'title' => 'Privát demó', 'summary' => 'Nem publikus', 'is_demo' => true, 'is_published' => true,
        ])->assertUnprocessable();
        $date = now()->addDays(5)->startOfHour();
        $this->actingAs($admin)->postJson('/api/admin/consultations', [
            'starts_at' => $date->toIso8601String(), 'duration_minutes' => 60,
        ])->assertCreated();
        $this->actingAs($admin)->postJson('/api/admin/consultations', [
            'starts_at' => $date->copy()->addMinutes(30)->toIso8601String(), 'duration_minutes' => 30,
        ])->assertUnprocessable();
        $this->actingAs($admin)->postJson('/api/admin/consultations', [
            'starts_at' => $date->copy()->addMinutes(60)->toIso8601String(), 'duration_minutes' => 30,
        ])->assertCreated();
    }

    public function test_admin_can_revoke_an_old_private_link_by_sending_a_new_one(): void
    {
        Mail::fake();
        config()->set('portfolio.mail_enabled', true);
        config()->set('mail.default', 'smtp');
        $admin = $this->admin();
        $quote = QuoteRequest::create($this->sampleQuoteForModel());
        $oldToken = bin2hex(random_bytes(32));
        $quote->portal_token_hash = hash('sha256', $oldToken);
        $quote->portal_token_expires_at = now()->addDays(30);
        $quote->save();
        $this->postJson('/api/client/project', ['token' => $oldToken])->assertOk();
        $this->actingAs($admin)->postJson('/api/admin/quote-requests/'.$quote->id.'/rotate-client-access')->assertOk();
        $this->postJson('/api/client/project', ['token' => $oldToken])->assertNotFound();
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ProjectAccessRenewed::class, 1);
    }

    public function test_private_project_access_expires_and_rejects_downloads_and_messages(): void
    {
        Mail::fake();
        config()->set('portfolio.mail_enabled', true);
        config()->set('mail.default', 'smtp');
        config()->set('portfolio.customer_receipt_enabled', true);
        $this->postJson('/api/quote-requests', $this->sampleQuote())->assertCreated();
        $token = null;
        Mail::assertSent(CustomerQuoteConfirmation::class, function ($mail) use (&$token) {
            $token = parse_url($mail->portalUrl, PHP_URL_FRAGMENT);
            return is_string($token);
        });
        $this->postJson('/api/client/project', ['token' => $token])->assertOk();
        $this->travel(91)->days();
        $this->postJson('/api/client/project', ['token' => $token])->assertNotFound();
        $this->postJson('/api/client/project/collaboration', ['token' => $token])->assertNotFound();
        $this->postJson('/api/client/project/messages', ['token' => $token, 'body' => 'Próba'])->assertNotFound();
        $this->postJson('/api/client/project/accept', ['token' => $token, 'accept_terms' => true])->assertNotFound();
    }

    public function test_change_password_invalidates_other_database_sessions(): void
    {
        config()->set('session.driver', 'database');
        $admin = $this->admin();
        \Illuminate\Support\Facades\DB::table('sessions')->insert([
            'id' => 'old-stolen-session', 'user_id' => $admin->id,
            'ip_address' => '127.0.0.1', 'user_agent' => 'Test',
            'payload' => 'e30=', 'last_activity' => now()->timestamp,
        ]);
        $this->actingAs($admin)->putJson('/api/admin/password', [
            'current_password' => 'password',
            'password' => 'a-new-strong-password-2026',
            'password_confirmation' => 'a-new-strong-password-2026',
        ])->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => 'old-stolen-session']);
    }

    public function test_admin_email_two_factor_blocks_api_until_correct_code(): void
    {
        Mail::fake();
        config()->set('portfolio.mail_enabled', true);
        config()->set('mail.default', 'smtp');
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/admin/security/two-factor', [
            'enabled' => true, 'current_password' => 'password',
        ])->assertOk();
        $this->postJson('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertStatus(202)->assertJsonPath('requires_two_factor', true);
        $this->getJson('/api/admin/me')->assertForbidden();
        $code = null;
        Mail::assertSent(\App\Mail\AdminLoginCode::class, function ($mail) use (&$code) {
            $code = $mail->code;
            return preg_match('/^[0-9]{6}$/', $code) === 1;
        });
        $this->postJson('/admin/verify-login', ['code' => 'abcdef'])->assertUnprocessable();
        $this->postJson('/admin/verify-login', ['code' => $code])->assertOk();
        $this->getJson('/api/admin/me')->assertOk();
    }

    private function sampleQuoteForModel(): array
    {
        return collect($this->sampleQuote())->except('privacy_notice_acknowledged')->all();
    }

    public function test_public_only_sees_published_projects_and_services(): void
    {
        Service::create(['slug'=>'hidden','title'=>'Hidden','summary'=>'Hidden service']);
        Service::create(['slug'=>'live','title'=>'Live','summary'=>'Live service','is_active'=>true]);
        PortfolioProject::create(['slug'=>'draft','title'=>'Draft','summary'=>'Draft project']);
        PortfolioProject::create(['slug'=>'published','title'=>'Published','summary'=>'Published project','is_demo'=>false,'is_published'=>true]);
        PortfolioProject::create(['slug'=>'demo-hidden','title'=>'Demo','summary'=>'Never shown','is_demo'=>true,'is_published'=>true]);
        $this->getJson('/api/services')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/projects')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/projects/draft')->assertNotFound();
        $this->getJson('/api/projects/demo-hidden')->assertNotFound();
        $this->getJson('/api/projects/published')->assertOk();
    }

    public function test_admin_can_create_portfolio_with_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $temp = tempnam(sys_get_temp_dir(), 'portfolio_');
        file_put_contents($temp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO9zOZAAAAAASUVORK5CYII='));
        $file = new UploadedFile($temp, 'demo.png', 'image/png', null, true);
        $result = $this->actingAs($admin)->post('/api/admin/projects', [
            'title' => 'Minta Projekt', 'summary' => 'Éttermi bemutatkozó oldal',
            'is_demo' => false, 'is_published' => true, 'cover_image' => $file,
        ], ['Accept' => 'application/json']);
        $result->assertCreated();
        $project = PortfolioProject::firstOrFail();
        Storage::disk('public')->assertExists($project->cover_image_path);
        @unlink($temp);
    }

    public function test_admin_can_change_site_settings(): void
    {
        $this->actingAs($this->admin())->patchJson('/api/admin/site-settings', [
            'hero_headline' => 'Weboldal neked',
        ])->assertOk();
        $this->getJson('/api/site-settings')->assertJsonPath('data.hero_headline', 'Weboldal neked');
    }
}
