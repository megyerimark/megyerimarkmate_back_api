<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminSecurityController;
use App\Http\Controllers\AdminWorkspaceController;
use App\Http\Controllers\AdminMaintenanceController;
use App\Http\Controllers\AdminMilestoneController;
use App\Http\Controllers\AdminPortfolioProjectController;
use App\Http\Controllers\AdminQuoteRequestController;
use App\Http\Controllers\AdminServiceController;
use App\Http\Controllers\AdminSiteSettingController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\ClientProjectController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\AdminConsultationController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\ProjectCollaborationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public read-only content. Unpublished projects and inactive services are excluded.
Route::get('/services', [PublicContentController::class, 'services'])->middleware('throttle:120,1');
Route::get('/projects', [PublicContentController::class, 'projects'])->middleware('throttle:120,1');
Route::get('/projects/{slug}', [PublicContentController::class, 'project'])->middleware('throttle:120,1');
Route::get('/site-settings', [PublicContentController::class, 'settings'])->middleware('throttle:120,1');

// Public enquiry submission: no personal information in the response besides the reference ID.
Route::post('/quote-requests', [QuoteRequestController::class, 'store'])->middleware('throttle:5,1');
// Private capability token lives in the request body (never in a server-logged URL).
Route::post('/client/project', [ClientProjectController::class, 'show'])->middleware('throttle:20,1');
Route::post('/client/project/accept', [ClientProjectController::class, 'accept'])->middleware('throttle:5,1');
Route::post('/client/project/collaboration', [ProjectCollaborationController::class, 'clientIndex'])->middleware('throttle:20,1');
Route::post('/client/project/messages', [ProjectCollaborationController::class, 'clientMessage'])->middleware('throttle:5,1');
Route::post('/client/project/documents/{document}/download', [ProjectCollaborationController::class, 'clientDownload'])->middleware('throttle:30,1');
Route::get('/consultations/available', [ConsultationController::class, 'available'])->middleware('throttle:40,1');
Route::post('/consultations/book', [ConsultationController::class, 'book'])->middleware('throttle:5,1');

Route::middleware(['auth:sanctum', 'can:access-admin', 'admin.2fa'])->prefix('admin')->group(function () {
    Route::get('/me', fn (Request $request) => response()->json([
        'data' => $request->user()->only(['id', 'name', 'email']),
    ]));
    Route::put('/password', [AdminAuthController::class, 'changePassword'])->middleware('throttle:5,1');
    Route::get('/security', [AdminSecurityController::class, 'status']);
    Route::put('/security/two-factor', [AdminSecurityController::class, 'setTwoFactor'])->middleware('throttle:5,1');
    Route::get('/security/sessions', [AdminSecurityController::class, 'sessions']);
    Route::delete('/security/sessions/others', [AdminSecurityController::class, 'revokeOtherSessions'])->middleware('throttle:5,1');
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/maintenance-agreements', [AdminMaintenanceController::class, 'index']);
    Route::post('/maintenance-agreements', [AdminMaintenanceController::class, 'store'])->middleware('throttle:20,1');
    Route::patch('/maintenance-agreements/{maintenanceAgreement}', [AdminMaintenanceController::class, 'update'])->middleware('throttle:20,1');
    Route::get('/quote-board', [AdminWorkspaceController::class, 'board']);
    Route::get('/workspace/projects', [AdminWorkspaceController::class, 'projects']);
    Route::get('/workspace/communications', [AdminWorkspaceController::class, 'communications']);
    Route::get('/workspace/tasks', [AdminWorkspaceController::class, 'tasks']);
    Route::post('/workspace/tasks', [AdminWorkspaceController::class, 'storeTask'])->middleware('throttle:30,1');
    Route::patch('/workspace/tasks/{studioTask}', [AdminWorkspaceController::class, 'updateTask']);
    Route::delete('/workspace/tasks/{studioTask}', [AdminWorkspaceController::class, 'destroyTask']);
    Route::get('/workspace/client-notes', [AdminWorkspaceController::class, 'notes']);
    Route::post('/workspace/client-notes', [AdminWorkspaceController::class, 'storeNote'])->middleware('throttle:30,1');
    Route::delete('/workspace/client-notes/{studioClientNote}', [AdminWorkspaceController::class, 'destroyNote']);
    Route::get('/clients', [AdminDashboardController::class, 'clients']);
    Route::get('/clients/quotes', [AdminDashboardController::class, 'clientQuotes']);
    Route::get('/activities', [AdminDashboardController::class, 'activities']);
    Route::get('/consultations', [AdminConsultationController::class, 'index']);
    Route::post('/consultations', [AdminConsultationController::class, 'store']);
    Route::delete('/consultations/{consultationSlot}', [AdminConsultationController::class, 'destroy']);

    Route::get('/quote-requests', [AdminQuoteRequestController::class, 'index']);
    Route::get('/quote-requests/{quoteRequest}', [AdminQuoteRequestController::class, 'show']);
    Route::patch('/quote-requests/{quoteRequest}', [AdminQuoteRequestController::class, 'update']);
    Route::post('/quote-requests/{quoteRequest}/send-proposal', [AdminQuoteRequestController::class, 'sendProposal'])->middleware('throttle:10,1');
    Route::post('/quote-requests/{quoteRequest}/rotate-client-access', [AdminQuoteRequestController::class, 'rotateClientAccess'])->middleware('throttle:5,1');
    Route::delete('/quote-requests/{quoteRequest}', [AdminQuoteRequestController::class, 'destroy']);
    Route::get('/quote-requests/{quoteRequest}/collaboration', [ProjectCollaborationController::class, 'adminIndex']);
    Route::post('/quote-requests/{quoteRequest}/messages', [ProjectCollaborationController::class, 'adminMessage']);
    Route::post('/quote-requests/{quoteRequest}/documents', [ProjectCollaborationController::class, 'adminUpload']);
    Route::get('/quote-requests/{quoteRequest}/documents/{document}/download', [ProjectCollaborationController::class, 'adminDownload']);
    Route::delete('/quote-requests/{quoteRequest}/documents/{document}', [ProjectCollaborationController::class, 'adminDelete']);
    Route::get('/quote-requests/{quoteRequest}/milestones', [AdminMilestoneController::class, 'index']);
    Route::post('/quote-requests/{quoteRequest}/milestones', [AdminMilestoneController::class, 'store']);
    Route::patch('/quote-requests/{quoteRequest}/milestones/{milestone}', [AdminMilestoneController::class, 'update']);
    Route::delete('/quote-requests/{quoteRequest}/milestones/{milestone}', [AdminMilestoneController::class, 'destroy']);

    Route::apiResource('services', AdminServiceController::class);
    Route::apiResource('projects', AdminPortfolioProjectController::class);
    Route::get('/site-settings', [AdminSiteSettingController::class, 'index']);
    Route::patch('/site-settings', [AdminSiteSettingController::class, 'update']);
});
