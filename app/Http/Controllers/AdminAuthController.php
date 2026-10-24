<?php

namespace App\Http\Controllers;

use App\Mail\AdminLoginCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt([
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => true,
        ])) {
            return response()->json(['message' => 'Hibás bejelentkezési adatok.'], 401);
        }

        $request->session()->regenerate();
        $request->session()->forget('admin_two_factor_verified');
        if ($request->user()->two_factor_email_enabled) {
            try {
                $this->sendLoginCode($request);
            } catch (Throwable $e) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return response()->json(['message' => 'A biztonsági kód kézbesítése sikertelen. Próbáld újra később.'], 503);
            }
            return response()->json(['message' => 'Ellenőrzőkódot küldtünk az admin e-mail-címére.', 'requires_two_factor' => true], 202);
        }
        return response()->json([
            'message' => 'Sikeres bejelentkezés.',
            'user' => $request->user()->only(['id', 'name', 'email']),
        ]);
    }

    private function sendLoginCode(Request $request): void
    {
        if (! config('portfolio.mail_enabled') || in_array(config('mail.default'), ['log', 'array', 'null'], true)) {
            throw new \RuntimeException('Real SMTP is required for two-factor authentication.');
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $request->session()->put('admin_two_factor_challenge', [
            'user_id' => $request->user()->id,
            'hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'attempts' => 0,
        ]);
        Mail::to($request->user()->email)->send(new AdminLoginCode($request->user()->name, $code));
    }

    public function verifyLogin(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'regex:/\A[0-9]{6}\z/D']]);
        $user = $request->user();
        abort_unless($user->is_admin, 403);
        $challenge = $request->session()->get('admin_two_factor_challenge');
        if (! $user->two_factor_email_enabled || ! is_array($challenge) ||
            ($challenge['user_id'] ?? null) !== $user->id ||
            ($challenge['expires_at'] ?? 0) < now()->timestamp ||
            ($challenge['attempts'] ?? 5) >= 5) {
            return response()->json(['message' => 'Az ellenőrzőkód lejárt. Jelentkezz be újra.'], 422);
        }
        if (! Hash::check($data['code'], $challenge['hash'])) {
            $challenge['attempts']++;
            $request->session()->put('admin_two_factor_challenge', $challenge);
            return response()->json(['message' => 'Hibás ellenőrzőkód.'], 422);
        }
        $request->session()->forget('admin_two_factor_challenge');
        $request->session()->regenerate();
        $request->session()->put('admin_two_factor_verified', $user->id);
        return response()->json(['message' => 'Sikeres kétlépcsős ellenőrzés.', 'user' => $user->only(['id', 'name', 'email'])]);
    }

    public function resendLoginCode(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_admin && $request->user()->two_factor_email_enabled, 403);
        try {
            $this->sendLoginCode($request);
        } catch (Throwable $e) {
            return response()->json(['message' => 'A kód kézbesítése sikertelen.'], 503);
        }
        return response()->json(['message' => 'Új ellenőrzőkódot küldtünk.']);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::guard('sanctum')->forgetUser();
        return response()->json(['message' => 'Sikeres kijelentkezés.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'A jelenlegi jelszó hibás.'], 422);
        }

        $user->password = Hash::make($data['password']);
        $user->remember_token = Str::random(60);
        $user->save();
        $request->session()->regenerate();
        // Stolen/forgotten browser sessions must not survive a password change.
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())->delete();
        }
        $user->tokens()->delete();
        return response()->json(['message' => 'A jelszó módosítva. A többi munkamenetet kijelentkeztettük.']);
    }
}
