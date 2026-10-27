<?php

namespace App\Http\Controllers;

use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Totp;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class AdminSecurityController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'two_factor_email_enabled' => (bool) $request->user()->two_factor_email_enabled,
            'two_factor_required' => (bool) config('portfolio.require_admin_2fa'),
            'totp_enabled' => $request->user()->hasTotp(),
            'recovery_codes_remaining' => $request->user()->hasTotp() ? count($request->user()->two_factor_recovery_codes ?? []) : 0,
            'session_driver' => config('session.driver'),
        ]])->header('Cache-Control', 'no-store');
    }

    public function setTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'current_password' => ['required', 'string'],
        ]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'A jelenlegi jelszó hibás.'], 422);
        }
        if (! $data['enabled'] && config('portfolio.require_admin_2fa')) {
            return response()->json(['message' => 'Éles környezetben a kétlépcsős belépés kötelező, nem kapcsolható ki.'], 422);
        }
        if ($data['enabled'] && ! \App\Support\Mailer::canSend()) {
            return response()->json(['message' => 'A kétlépcsős belépéshez működő SMTP-küldést kell beállítani.'], 503);
        }
        $request->user()->forceFill(['two_factor_email_enabled' => $data['enabled']])->save();
        if ($request->hasSession()) $request->session()->put('admin_two_factor_verified', $request->user()->id);
        if (config('session.driver') === 'database') {
            $others = DB::table('sessions')->where('user_id', $request->user()->id);
            if ($request->hasSession()) $others->where('id', '!=', $request->session()->getId());
            $others->delete();
        }
        AdminAudit::record($request, $data['enabled'] ? 'two_factor_enabled' : 'two_factor_disabled', 'user', $request->user()->id);
        return response()->json(['message' => $data['enabled'] ? 'E-mailes kétlépcsős védelem bekapcsolva.' : 'Kétlépcsős védelem kikapcsolva.', 'data' => ['two_factor_email_enabled' => $data['enabled']]]);
    }

    /** 1. lépés: új titok a hitelesítő alkalmazáshoz (még nem aktív, csak a munkamenetben él). */
    public function totpSetup(Request $request): JsonResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string']]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'A jelenlegi jelszó hibás.'], 422);
        }
        if (! $request->hasSession()) {
            return response()->json(['message' => 'Ehhez a művelethez munkamenetes bejelentkezés szükséges.'], 409);
        }
        $secret = Totp::generateSecret();
        $request->session()->put('admin_totp_pending', ['secret' => Crypt::encryptString($secret), 'expires_at' => now()->addMinutes(15)->timestamp]);
        $issuer = (string) (config('app.name') ?: 'Admin');
        return response()->json(['data' => [
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'otpauth_uri' => Totp::uri($secret, $request->user()->email, $issuer),
        ]])->header('Cache-Control', 'no-store');
    }

    /** 2. lépés: az alkalmazás első kódjával megerősítve bekapcsol; a helyreállítási kódokat egyszer adjuk vissza. */
    public function totpConfirm(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/D']]);
        $pending = $request->hasSession() ? $request->session()->get('admin_totp_pending') : null;
        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            return response()->json(['message' => 'A beállítás lejárt. Kezdd újra.'], 422);
        }
        $secret = Crypt::decryptString($pending['secret']);
        $step = Totp::verify($secret, $data['code']);
        if ($step === null) {
            return response()->json(['message' => 'Hibás kód. Ellenőrizd, hogy a telefonod órája pontos-e.'], 422);
        }
        $user = $request->user();
        $user->forceFill([
            'two_factor_totp_secret' => $secret,
            'two_factor_totp_confirmed_at' => now(),
            'two_factor_totp_last_step' => $step,
        ])->save();
        $codes = $user->regenerateRecoveryCodes();
        $request->session()->forget('admin_totp_pending');
        $request->session()->put('admin_two_factor_verified', $user->id);
        $this->logoutOtherSessions($request);
        AdminAudit::record($request, 'totp_enabled', 'user', $user->id);
        return response()->json([
            'message' => 'Hitelesítő alkalmazásos belépés bekapcsolva. Mentsd el a helyreállítási kódokat!',
            'data' => ['recovery_codes' => $codes],
        ])->header('Cache-Control', 'no-store');
    }

    /** Kikapcsolás jelszóval + érvényes kóddal. Kötelező 2FA esetén ezután újra az e-mailes kód lép életbe. */
    public function totpDisable(Request $request): JsonResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string'], 'code' => ['required', 'string', 'max:20']]);
        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'A jelenlegi jelszó hibás.'], 422);
        }
        if (! $user->hasTotp() || $user->consumeSecondFactor($data['code']) === null) {
            return response()->json(['message' => 'Hibás hitelesítő- vagy helyreállítási kód.'], 422);
        }
        $user->forceFill([
            'two_factor_totp_secret' => null, 'two_factor_totp_confirmed_at' => null,
            'two_factor_recovery_codes' => null, 'two_factor_totp_last_step' => null,
        ])->save();
        AdminAudit::record($request, 'totp_disabled', 'user', $user->id);
        return response()->json(['message' => 'Hitelesítő alkalmazásos belépés kikapcsolva.']);
    }

    /** Új helyreállítási kódok (a régiek érvénytelenné válnak). */
    public function totpRecoveryCodes(Request $request): JsonResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string']]);
        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'A jelenlegi jelszó hibás.'], 422);
        }
        if (! $user->hasTotp()) {
            return response()->json(['message' => 'Előbb kapcsold be a hitelesítő alkalmazásos belépést.'], 422);
        }
        $codes = $user->regenerateRecoveryCodes();
        AdminAudit::record($request, 'totp_recovery_regenerated', 'user', $user->id);
        return response()->json(['message' => 'Új helyreállítási kódok. A korábbiak érvénytelenek.', 'data' => ['recovery_codes' => $codes]])
            ->header('Cache-Control', 'no-store');
    }

    private function logoutOtherSessions(Request $request): void
    {
        if (config('session.driver') === 'database') {
            $others = DB::table('sessions')->where('user_id', $request->user()->id);
            if ($request->hasSession()) $others->where('id', '!=', $request->session()->getId());
            $others->delete();
        }
    }

    public function sessions(Request $request): JsonResponse
    {
        if (config('session.driver') !== 'database') {
            return response()->json(['message' => 'A munkamenetlista adatbázis-alapú SESSION_DRIVER használatával érhető el.'], 409);
        }

        if (! $request->hasSession()) {
            return response()->json(['message' => 'Ehhez a művelethez munkamenetes bejelentkezés szükséges.'], 409);
        }
        $current = $request->session()->getId();
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')->limit(50)
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($session) => [
                // Never expose the actual session ID to the browser.
                'id' => hash('sha256', $session->id),
                'ip_address' => $session->ip_address,
                'user_agent' => mb_substr((string) $session->user_agent, 0, 200),
                'last_activity' => $session->last_activity,
                'current' => $session->id === $current,
            ]);
        return response()->json(['data' => $sessions])->header('Cache-Control', 'no-store');
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        if (config('session.driver') !== 'database') {
            return response()->json(['message' => 'Adatbázis-alapú munkamenettárolás szükséges.'], 409);
        }
        if (! $request->hasSession()) {
            return response()->json(['message' => 'Ehhez a művelethez munkamenetes bejelentkezés szükséges.'], 409);
        }
        $removed = DB::table('sessions')->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())->delete();
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'A többi munkamenetet kijelentkeztettük.', 'removed' => $removed]);
    }
}
