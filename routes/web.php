<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminPasswordResetController;
use App\Http\Controllers\PublicContentController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'Megyeri Márk | Webfejlesztés API',
    'status' => 'ok',
]));
// Oldaltérkép a gyökérben is (a frontend .htaccess-e ide irányítja): munkamenet és süti nélkül.
Route::get('/sitemap.xml', [PublicContentController::class, 'sitemap'])
    ->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class, \Illuminate\View\Middleware\ShareErrorsFromSession::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Cookie\Middleware\EncryptCookies::class, \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class])
    ->middleware('throttle:60,1,get--root-sitemap.xml');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware(['throttle:admin-login', 'turnstile']);
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->middleware('auth');
Route::post('/admin/verify-login', [AdminAuthController::class, 'verifyLogin'])->middleware(['auth', 'throttle:5,1,post--admin-verify-login']);
Route::post('/admin/resend-login-code', [AdminAuthController::class, 'resendLoginCode'])->middleware(['auth', 'throttle:2,1,post--admin-resend-login-code']);

Route::post('/admin/forgot-password', [AdminPasswordResetController::class, 'requestLink'])->middleware(['throttle:admin-password-reset', 'turnstile']);
Route::post('/admin/reset-password', [AdminPasswordResetController::class, 'reset'])->middleware('throttle:5,1,post--admin-reset-password');
