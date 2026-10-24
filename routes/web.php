<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminPasswordResetController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'Megyeri Márk | Webfejlesztés API',
    'status' => 'ok',
]));
Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->middleware('auth');
Route::post('/admin/verify-login', [AdminAuthController::class, 'verifyLogin'])->middleware(['auth', 'throttle:5,1']);
Route::post('/admin/resend-login-code', [AdminAuthController::class, 'resendLoginCode'])->middleware(['auth', 'throttle:2,1']);

Route::post('/admin/forgot-password', [AdminPasswordResetController::class, 'requestLink'])->middleware('throttle:3,1');
Route::post('/admin/reset-password', [AdminPasswordResetController::class, 'reset'])->middleware('throttle:5,1');
