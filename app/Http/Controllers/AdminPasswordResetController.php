<?php

namespace App\Http\Controllers;

use App\Mail\AdminPasswordResetLink;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Throwable;

class AdminPasswordResetController extends Controller
{
    public function requestLink(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:254']]);
        $generic = ['message' => 'Ha létezik jogosult adminfiók ehhez a címhez, elküldtük a jelszó-visszaállítási útmutatót.'];
        $user = User::query()->where('email', $data['email'])->where('is_admin', true)->first();
        if (! $user) {
            return response()->json($generic);
        }
        try {
            $token = Password::broker('users')->createToken($user);
            $url = rtrim(config('portfolio.frontend_url'), '/') . '/admin/jelszo-visszaallitas?email='
                . rawurlencode($user->email) . '#token=' . rawurlencode($token);
            Mail::to($user->email)->send(new AdminPasswordResetLink($user->name, $url));
        } catch (Throwable $e) {
            // Never record the password reset token or link.
            Log::error('Admin password recovery mail failed', ['exception' => get_class($e)]);
        }
        return response()->json($generic);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)],
        ]);
        if (! User::query()->where('email', $data['email'])->where('is_admin', true)->exists()) {
            return response()->json(['message' => 'A link érvénytelen vagy lejárt.'], 422);
        }
        $status = Password::broker('users')->reset($data, function (User $user, string $password) {
            if (! $user->is_admin) {
                return;
            }
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            // Invalidate existing sessions and personal access tokens after a credential reset.
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'A link érvénytelen vagy lejárt. Kérj új visszaállító linket.'], 422);
        }
        return response()->json(['message' => 'A jelszó módosítva. Most már bejelentkezhetsz.']);
    }
}
