<?php

namespace App\Http\Controllers;

use App\Support\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSecurityController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'two_factor_email_enabled' => (bool) $request->user()->two_factor_email_enabled,
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
        if ($data['enabled'] && (! config('portfolio.mail_enabled') || in_array(config('mail.default'), ['log', 'array', 'null'], true))) {
            return response()->json(['message' => 'A kétlépcsős belépéshez működő SMTP-küldést kell beállítani.'], 503);
        }
        $request->user()->forceFill(['two_factor_email_enabled' => $data['enabled']])->save();
        $request->session()->put('admin_two_factor_verified', $request->user()->id);
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())->delete();
        }
        AdminAudit::record($request, $data['enabled'] ? 'two_factor_enabled' : 'two_factor_disabled', 'user', $request->user()->id);
        return response()->json(['message' => $data['enabled'] ? 'E-mailes kétlépcsős védelem bekapcsolva.' : 'Kétlépcsős védelem kikapcsolva.', 'data' => ['two_factor_email_enabled' => $data['enabled']]]);
    }

    public function sessions(Request $request): JsonResponse
    {
        if (config('session.driver') !== 'database') {
            return response()->json(['message' => 'A munkamenetlista adatbázis-alapú SESSION_DRIVER használatával érhető el.'], 409);
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
        $removed = DB::table('sessions')->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())->delete();
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'A többi munkamenetet kijelentkeztettük.', 'removed' => $removed]);
    }
}
