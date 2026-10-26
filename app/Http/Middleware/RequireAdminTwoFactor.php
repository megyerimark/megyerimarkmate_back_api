<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        // Sanctum may authenticate token/API requests without starting a session.
        // A missing session must never bypass the second factor or cause a 500.
        if ($user?->requiresTwoFactor() && (! $request->hasSession() ||
            $request->session()->get('admin_two_factor_verified') !== $user->id)) {
            return response()->json(['message' => 'Kétlépcsős ellenőrzés szükséges.'], 403);
        }
        return $next($request);
    }
}
