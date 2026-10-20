<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => true,
        ])) {
            return response()->json(['message' => 'Hibás bejelentkezési adatok.'], 401);
        }

        $request->session()->regenerate();
        return response()->json([
            'message' => 'Sikeres bejelentkezés.',
            'user' => $request->user()->only(['id', 'name', 'email']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
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
        $user->save();
        $request->session()->regenerate();
        return response()->json(['message' => 'A jelszó módosítva.']);
    }
}
