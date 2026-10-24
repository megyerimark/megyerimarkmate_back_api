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
        if ($user?->two_factor_email_enabled && $request->session()->get('admin_two_factor_verified') !== $user->id) {
            return response()->json(['message' => 'Kétlépcsős ellenőrzés szükséges.'], 403);
        }
        return $next($request);
    }
}
