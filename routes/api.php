<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminPortfolioProjectController;
use App\Http\Controllers\AdminQuoteRequestController;
use App\Http\Controllers\AdminServiceController;
use App\Http\Controllers\AdminSiteSettingController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\QuoteRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public read-only content. Unpublished projects and inactive services are excluded.
Route::get('/services', [PublicContentController::class, 'services']);
Route::get('/projects', [PublicContentController::class, 'projects']);
Route::get('/projects/{slug}', [PublicContentController::class, 'project']);
Route::get('/site-settings', [PublicContentController::class, 'settings']);

// Public enquiry submission: no personal information in the response besides the reference ID.
Route::post('/quote-requests', [QuoteRequestController::class, 'store'])->middleware('throttle:5,1');

Route::middleware(['auth:sanctum', 'can:access-admin'])->prefix('admin')->group(function () {
    Route::get('/me', fn (Request $request) => response()->json([
        'data' => $request->user()->only(['id', 'name', 'email']),
    ]));
    Route::put('/password', [AdminAuthController::class, 'changePassword'])->middleware('throttle:5,1');
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/clients', [AdminDashboardController::class, 'clients']);
    Route::get('/clients/quotes', [AdminDashboardController::class, 'clientQuotes']);
    Route::get('/activities', [AdminDashboardController::class, 'activities']);

    Route::get('/quote-requests', [AdminQuoteRequestController::class, 'index']);
    Route::get('/quote-requests/{quoteRequest}', [AdminQuoteRequestController::class, 'show']);
    Route::patch('/quote-requests/{quoteRequest}', [AdminQuoteRequestController::class, 'update']);
    Route::delete('/quote-requests/{quoteRequest}', [AdminQuoteRequestController::class, 'destroy']);

    Route::apiResource('services', AdminServiceController::class);
    Route::apiResource('projects', AdminPortfolioProjectController::class);
    Route::get('/site-settings', [AdminSiteSettingController::class, 'index']);
    Route::patch('/site-settings', [AdminSiteSettingController::class, 'update']);
});
