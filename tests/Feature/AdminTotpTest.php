<?php

namespace Tests\Feature;

use App\Mail\AdminLoginCode;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminTotpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Az Angular SPA Origin fejlécet küld: így a Sanctum ugyanazt a munkamenetet kapcsolja az API-kérésekhez.
        $this->withHeader('Origin', config('app.url'));
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@example.com', 'password' => 'correct-horse-battery']);
        $u->forceFill(['is_admin' => true])->save();
        return $u;
    }

    private function enableTotp(User $u): string
    {
        $secret = Totp::generateSecret();
        $u->forceFill(['two_factor_totp_secret' => $secret, 'two_factor_totp_confirmed_at' => now()])->save();
        return $secret;
    }

    private function login(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/admin/login', ['email' => 'admin@example.com', 'password' => 'correct-horse-battery']);
    }

    public function test_rfc6238_reference_vector(): void
    {
        // RFC 6238 B. függelék, SHA1, T = 59 s → 94287082 (8 jegy); 6 jegyen: 287082.
        $secret = Totp::base32Encode('12345678901234567890');
        $this->assertSame('287082', Totp::code($secret, intdiv(59, 30)));
        $this->assertSame('12345678901234567890', Totp::base32Decode($secret));
    }

    public function test_setup_and_confirm_enables_totp_and_returns_recovery_codes(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->withSession(['admin_two_factor_verified' => $admin->id]);

        $this->postJson('/api/admin/security/totp/setup', ['current_password' => 'wrong'])->assertUnprocessable();
        $setup = $this->postJson('/api/admin/security/totp/setup', ['current_password' => 'correct-horse-battery'])->assertOk();
        $this->assertStringStartsWith('otpauth://totp/', $setup->json('data.otpauth_uri'));
        $secret = str_replace(' ', '', $setup->json('data.secret'));

        $this->postJson('/api/admin/security/totp/confirm', ['code' => '000000'])->assertUnprocessable();
        $codes = $this->postJson('/api/admin/security/totp/confirm', ['code' => Totp::code($secret, intdiv(time(), 30))])
            ->assertOk()->json('data.recovery_codes');
        $this->assertCount(10, $codes);
        $this->assertTrue($admin->fresh()->hasTotp());
        $this->getJson('/api/admin/security')->assertJsonPath('data.totp_enabled', true)->assertJsonPath('data.recovery_codes_remaining', 10);
        // A titok sosem kerül ki a felhasználó JSON-jában.
        $this->assertArrayNotHasKey('two_factor_totp_secret', $admin->fresh()->toArray());
    }

    public function test_login_with_totp_sends_no_email_and_accepts_app_code_once(): void
    {
        Mail::fake();
        config()->set(['portfolio.require_admin_2fa' => true, 'portfolio.mail_enabled' => true, 'mail.default' => 'smtp']);
        $secret = $this->enableTotp($this->admin());

        $this->login()->assertStatus(202)->assertJsonPath('two_factor_method', 'totp');
        Mail::assertNothingSent();
        $this->getJson('/api/admin/me')->assertForbidden();

        $code = Totp::code($secret, intdiv(time(), 30));
        $this->postJson('/admin/verify-login', ['code' => $code])->assertOk();
        $this->getJson('/api/admin/me')->assertOk();

        // Ugyanaz a kód nem használható újra (visszajátszás).
        $this->postJson('/admin/logout')->assertOk();
        $this->login()->assertStatus(202);
        $this->postJson('/admin/verify-login', ['code' => $code])->assertUnprocessable();
    }

    public function test_login_works_with_totp_even_without_smtp(): void
    {
        config()->set(['portfolio.require_admin_2fa' => true, 'portfolio.mail_enabled' => false]);
        $secret = $this->enableTotp($this->admin());
        $this->login()->assertStatus(202);
        $this->postJson('/admin/verify-login', ['code' => Totp::code($secret, intdiv(time(), 30))])->assertOk();
    }

    public function test_recovery_code_works_exactly_once(): void
    {
        $admin = $this->admin();
        $this->enableTotp($admin);
        $codes = $admin->regenerateRecoveryCodes();

        $this->login()->assertStatus(202);
        $this->postJson('/admin/verify-login', ['code' => strtolower($codes[0])])->assertOk();
        $this->assertCount(9, $admin->fresh()->two_factor_recovery_codes);

        $this->postJson('/admin/logout')->assertOk();
        $this->login()->assertStatus(202);
        $this->postJson('/admin/verify-login', ['code' => $codes[0]])->assertUnprocessable();
    }

    public function test_email_code_cannot_be_used_when_totp_is_enabled_and_resend_is_refused(): void
    {
        Mail::fake();
        config()->set(['portfolio.require_admin_2fa' => true, 'portfolio.mail_enabled' => true, 'mail.default' => 'smtp']);
        $this->enableTotp($this->admin());
        $this->login()->assertStatus(202);
        $this->postJson('/admin/resend-login-code')->assertUnprocessable();
        Mail::assertNotSent(AdminLoginCode::class);
    }

    public function test_disable_requires_password_and_valid_code(): void
    {
        $admin = $this->admin();
        $secret = $this->enableTotp($admin);
        $this->actingAs($admin)->withSession(['admin_two_factor_verified' => $admin->id]);

        $this->postJson('/api/admin/security/totp/disable', ['current_password' => 'correct-horse-battery', 'code' => '123456'])->assertUnprocessable();
        $this->postJson('/api/admin/security/totp/disable', [
            'current_password' => 'correct-horse-battery', 'code' => Totp::code($secret, intdiv(time(), 30)),
        ])->assertOk();
        $this->assertFalse($admin->fresh()->hasTotp());
    }
}
