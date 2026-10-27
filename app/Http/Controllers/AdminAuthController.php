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
        if ($request->user()->hasTotp()) {
            // Hitelesítő alkalmazás: nincs e-mail, a kódot az app adja (vagy egy helyreállítási kód).
            $request->session()->put('admin_two_factor_challenge', [
                'user_id' => $request->user()->id,
                'method' => 'totp',
                'expires_at' => now()->addMinutes(10)->timestamp,
                'attempts' => 0,
            ]);
            return response()->json([
                'message' => 'Add meg a hitelesítő alkalmazásod kódját.',
                'requires_two_factor' => true, 'two_factor_method' => 'totp',
            ], 202);
        }
        if ($request->user()->requiresTwoFactor()) {
            try {
                $this->sendLoginCode($request);
            } catch (Throwable $e) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return response()->json(['message' => 'A biztonsági kód kézbesítése sikertelen. Próbáld újra később.'], 503);
            }
            return response()->json(['message' => 'Ellenőrzőkódot küldtünk az admin e-mail-címére.', 'requires_two_factor' => true, 'two_factor_method' => 'email'], 202);
        }
        return response()->json([
            'message' => 'Sikeres bejelentkezés.',
            'user' => $request->user()->only(['id', 'name', 'email']),
        ]);
    }

    private function sendLoginCode(Request $request): void
    {
        if (! \App\Support\Mailer::canSend()) {
            throw new \RuntimeException('Real SMTP is required for two-factor authentication.');
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $request->session()->put('admin_two_factor_challenge', [
            'user_id' => $request->user()->id,
            'method' => 'email',
            'hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'attempts' => 0,
        ]);
        Mail::to($request->user()->email)->send(new AdminLoginCode($request->user()->name, $code));
    }

    public function verifyLogin(Request $request): JsonResponse
    {
        // 6 számjegy (e-mail / hitelesítő app) vagy XXXXX-XXXXX helyreállítási kód.
        $data = $request->validate(['code' => ['required', 'string', 'max:20', 'regex:/\A([0-9]{6}|[A-Za-z0-9]{5}-?[A-Za-z0-9]{5})\z/D']]);
        $user = $request->user();
        abort_unless($user->is_admin, 403);
        $challenge = $request->session()->get('admin_two_factor_challenge');
        if (! $user->requiresTwoFactor() || ! is_array($challenge) ||
            ($challenge['user_id'] ?? null) !== $user->id ||
            ($challenge['expires_at'] ?? 0) < now()->timestamp ||
            ($challenge['attempts'] ?? 5) >= 5) {
            return response()->json(['message' => 'Az ellenőrzőkód lejárt. Jelentkezz be újra.'], 422);
        }
        $method = $challenge['method'] ?? 'email';
        // Ha közben bekapcsolták a hitelesítő appot, régi e-mailes kihívással nem lehet belépni.
        if ($user->hasTotp() !== ($method === 'totp')) {
            $request->session()->forget('admin_two_factor_challenge');
            return response()->json(['message' => 'Az ellenőrzőkód lejárt. Jelentkezz be újra.'], 422);
        }
        $valid = $method === 'totp'
            ? $user->consumeSecondFactor($data['code']) !== null
            : (preg_match('/\A[0-9]{6}\z/D', $data['code']) === 1 && Hash::check($data['code'], $challenge['hash'] ?? ''));
        if (! $valid) {
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
        abort_unless($request->user()->is_admin && $request->user()->requiresTwoFactor(), 403);
        if ($request->user()->hasTotp()) {
            return response()->json(['message' => 'Hitelesítő alkalmazás van beállítva: a kódot az alkalmazásban látod. Ha nincs nálad, használj helyreállítási kódot.'], 422);
        }
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
        if ($request->hasSession()) $request->session()->regenerate();
        // Stolen/forgotten browser sessions must not survive a password change.
        if (config('session.driver') === 'database') {
            $others = DB::table('sessions')->where('user_id', $user->id);
            if ($request->hasSession()) $others->where('id', '!=', $request->session()->getId());
            $others->delete();
        }
        $user->tokens()->delete();
        return response()->json(['message' => 'A jelszó módosítva. A többi munkamenetet kijelentkeztettük.']);
    }
}
