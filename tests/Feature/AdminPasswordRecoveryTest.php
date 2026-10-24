<?php

namespace Tests\Feature;

use App\Mail\AdminPasswordResetLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminPasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgotten_password_reply_does_not_reveal_whether_admin_exists(): void
    {
        Mail::fake();
        $this->postJson('/admin/forgot-password', ['email' => 'unknown@example.com'])->assertOk();
        Mail::assertNothingSent();
    }

    public function test_only_admin_receives_reset_link_and_can_set_new_password(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['email' => 'admin@example.com', 'password' => bcrypt('old-password')]);
        $admin->is_admin = true;
        $admin->save();
        $this->postJson('/admin/forgot-password', ['email' => $admin->email])->assertOk();
        Mail::assertSent(AdminPasswordResetLink::class, 1);
        $url = null;
        Mail::assertSent(AdminPasswordResetLink::class, function ($mailable) use ($admin, &$url) {
            $url = $mailable->resetUrl;
            return $mailable->hasTo($admin->email);
        });
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        parse_str($fragment, $params);
        $this->postJson('/admin/reset-password', [
            'email' => $admin->email,
            'token' => $params['token'],
            'password' => 'new-strong-password-2026',
            'password_confirmation' => 'new-strong-password-2026',
        ])->assertOk();
        $this->postJson('/admin/login', ['email' => $admin->email, 'password' => 'new-strong-password-2026'])->assertOk();
    }

    public function test_non_admin_cannot_receive_password_reset_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'user@example.com']);
        $user->is_admin = false;
        $user->save();
        $this->postJson('/admin/forgot-password', ['email' => $user->email])->assertOk();
        Mail::assertNothingSent();
    }
}
