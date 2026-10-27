<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminClientSiteController;
use App\Http\Controllers\AdminFinanceController;
use App\Http\Controllers\AdminSystemStatusController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminSecurityController;
use App\Http\Controllers\AdminWorkspaceController;
use App\Http\Controllers\AdminMaintenanceController;
use App\Http\Controllers\AdminMilestoneController;
use App\Http\Controllers\AdminPortfolioProjectController;
use App\Http\Controllers\AdminGrowthController;
use App\Http\Controllers\AdminTestimonialController;
use App\Http\Controllers\PublicEventController;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use App\Http\Controllers\AdminQuoteRequestController;
use App\Http\Controllers\AdminServiceController;
use App\Http\Controllers\AdminSiteSettingController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\ClientProjectController;
use App\Http\Controllers\ClientPaymentController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\AdminConsultationController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\ProjectCollaborationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public read-only content. Unpublished projects and inactive services are excluded.
// A látogatók névtelenek: ezek az útvonalak nem indítanak munkamenetet és nem állítanak be sütit.
Route::withoutMiddleware([EnsureFrontendRequestsAreStateful::class])->group(function () {
    Route::get('/services', [PublicContentController::class, 'services'])->middleware('throttle:120,1,get--services');
    Route::get('/projects', [PublicContentController::class, 'projects'])->middleware('throttle:120,1,get--projects');
    Route::get('/projects/{slug}', [PublicContentController::class, 'project'])->middleware('throttle:120,1,get--projects-slug');
    Route::get('/site-settings', [PublicContentController::class, 'settings'])->middleware('throttle:120,1,get--site-settings');
    Route::get('/testimonials', [PublicContentController::class, 'testimonials'])->middleware('throttle:120,1,get--testimonials');
    Route::get('/sitemap.xml', [PublicContentController::class, 'sitemap'])->middleware('throttle:60,1,get--sitemap.xml');
    // Sütik és személyes adatok nélküli, aggregált eseményszámlálás.
    Route::post('/events', [PublicEventController::class, 'store'])->middleware('throttle:120,1,post--events');
});

// Public enquiry submission: no personal information in the response besides the reference ID.
Route::post('/quote-requests', [QuoteRequestController::class, 'store'])->middleware('throttle:5,1,post--quote-requests');
// Hírlevél: kettős megerősítés; a kulcsok a kérés törzsében utaznak.
Route::post('/newsletter/subscribe', [\App\Http\Controllers\NewsletterController::class, 'subscribe'])->middleware('throttle:5,1,post--newsletter-subscribe');
Route::post('/newsletter/confirm', [\App\Http\Controllers\NewsletterController::class, 'confirm'])->middleware('throttle:20,1,post--newsletter-confirm');
Route::post('/newsletter/unsubscribe', [\App\Http\Controllers\NewsletterController::class, 'unsubscribe'])->middleware('throttle:20,1,post--newsletter-unsubscribe');
// Private capability token lives in the request body (never in a server-logged URL).
Route::post('/client/project', [ClientProjectController::class, 'show'])->middleware('throttle:20,1,post--client-project');
Route::post('/client/project/accept', [ClientProjectController::class, 'accept'])->middleware('throttle:5,1,post--client-project-accept');
Route::post('/client/project/collaboration', [ProjectCollaborationController::class, 'clientIndex'])->middleware('throttle:20,1,post--client-project-collaboration');
Route::post('/client/project/messages', [ProjectCollaborationController::class, 'clientMessage'])->middleware('throttle:5,1,post--client-project-messages');
Route::post('/client/project/documents/{document}/download', [ProjectCollaborationController::class, 'clientDownload'])->middleware('throttle:30,1,post--client-project-documents-document-download');
Route::post('/client/project/deposit/checkout', [ClientPaymentController::class, 'depositCheckout'])->middleware('throttle:10,1,post--client-project-deposit-checkout');
Route::post('/client/project/maintenance/checkout', [ClientPaymentController::class, 'maintenanceCheckout'])->middleware('throttle:10,1,post--client-project-maintenance-checkout');
Route::post('/client/project/maintenance/portal', [ClientPaymentController::class, 'billingPortal'])->middleware('throttle:10,1,post--client-project-maintenance-portal');
// Stripe → szerver (aláírt események). Nincs munkamenet és CSRF; az aláírás a védelem.
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class])->middleware('throttle:300,1,post--stripe-webhook');
Route::get('/consultations/available', [ConsultationController::class, 'available'])->middleware('throttle:40,1,get--consultations-available');
Route::post('/consultations/book', [ConsultationController::class, 'book'])->middleware('throttle:5,1,post--consultations-book');

Route::middleware(['auth:sanctum', 'can:access-admin', 'admin.2fa'])->prefix('admin')->group(function () {
    Route::get('/me', fn (Request $request) => response()->json([
        'data' => $request->user()->only(['id', 'name', 'email']),
    ]));
    Route::put('/password', [AdminAuthController::class, 'changePassword'])->middleware('throttle:5,1,put--password');
    Route::get('/security', [AdminSecurityController::class, 'status']);
    Route::get('/system-status', [AdminSystemStatusController::class, 'index']);
    Route::put('/security/two-factor', [AdminSecurityController::class, 'setTwoFactor'])->middleware('throttle:5,1,put--security-two-factor');
    Route::post('/security/totp/setup', [AdminSecurityController::class, 'totpSetup'])->middleware('throttle:5,1,post--security-totp-setup');
    Route::post('/security/totp/confirm', [AdminSecurityController::class, 'totpConfirm'])->middleware('throttle:5,1,post--security-totp-confirm');
    Route::post('/security/totp/disable', [AdminSecurityController::class, 'totpDisable'])->middleware('throttle:5,1,post--security-totp-disable');
    Route::post('/security/totp/recovery-codes', [AdminSecurityController::class, 'totpRecoveryCodes'])->middleware('throttle:5,1,post--security-totp-recovery-codes');
    Route::get('/security/sessions', [AdminSecurityController::class, 'sessions']);
    Route::delete('/security/sessions/others', [AdminSecurityController::class, 'revokeOtherSessions'])->middleware('throttle:5,1,delete--security-sessions-others');
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/maintenance-agreements', [AdminMaintenanceController::class, 'index']);
    Route::post('/maintenance-agreements', [AdminMaintenanceController::class, 'store'])->middleware('throttle:20,1,post--maintenance-agreements');
    Route::patch('/maintenance-agreements/{maintenanceAgreement}', [AdminMaintenanceController::class, 'update'])->middleware('throttle:20,1,patch--maintenance-agreements-maintenanceAgreement');
    Route::post('/maintenance-agreements/{maintenanceAgreement}/cancel-subscription', [AdminMaintenanceController::class, 'cancelSubscription'])->middleware('throttle:5,1,post--maintenance-agreements-maintenanceAgreement-cancel-subscription');
    Route::get('/quote-board', [AdminWorkspaceController::class, 'board']);
    Route::get('/workspace/projects', [AdminWorkspaceController::class, 'projects']);
    Route::get('/workspace/communications', [AdminWorkspaceController::class, 'communications']);
    Route::get('/workspace/tasks', [AdminWorkspaceController::class, 'tasks']);
    Route::post('/workspace/tasks', [AdminWorkspaceController::class, 'storeTask'])->middleware('throttle:30,1,post--workspace-tasks');
    Route::patch('/workspace/tasks/{studioTask}', [AdminWorkspaceController::class, 'updateTask']);
    Route::delete('/workspace/tasks/{studioTask}', [AdminWorkspaceController::class, 'destroyTask']);
    Route::get('/workspace/client-notes', [AdminWorkspaceController::class, 'notes']);
    Route::post('/workspace/client-notes', [AdminWorkspaceController::class, 'storeNote'])->middleware('throttle:30,1,post--workspace-client-notes');
    Route::delete('/workspace/client-notes/{studioClientNote}', [AdminWorkspaceController::class, 'destroyNote']);
    Route::get('/clients', [AdminDashboardController::class, 'clients']);
    Route::get('/clients/quotes', [AdminDashboardController::class, 'clientQuotes']);
    Route::get('/activities', [AdminDashboardController::class, 'activities']);
    Route::get('/consultations', [AdminConsultationController::class, 'index']);
    Route::post('/consultations', [AdminConsultationController::class, 'store']);
    Route::delete('/consultations/{consultationSlot}', [AdminConsultationController::class, 'destroy']);

    Route::get('/growth', [AdminGrowthController::class, 'index']);
    Route::get('/sites', [AdminClientSiteController::class, 'index']);
    Route::post('/sites', [AdminClientSiteController::class, 'store'])->middleware('throttle:30,1,post--sites');
    Route::patch('/sites/{clientSite}', [AdminClientSiteController::class, 'update'])->middleware('throttle:60,1,patch--sites-clientSite');
    Route::delete('/sites/{clientSite}', [AdminClientSiteController::class, 'destroy']);
    Route::get('/vault', [AdminClientSiteController::class, 'vault']);
    Route::get('/finance/summary', [AdminFinanceController::class, 'summary']);
    Route::get('/finance/entries', [AdminFinanceController::class, 'entries']);
    Route::post('/finance/entries', [AdminFinanceController::class, 'store'])->middleware('throttle:60,1,post--finance-entries');
    Route::patch('/finance/entries/{financeEntry}', [AdminFinanceController::class, 'update'])->middleware('throttle:60,1,patch--finance-entries-financeEntry');
    Route::delete('/finance/entries/{financeEntry}', [AdminFinanceController::class, 'destroy']);
    Route::put('/finance/settings', [AdminFinanceController::class, 'updateSettings'])->middleware('throttle:20,1,put--finance-settings');
    Route::put('/vault', [AdminClientSiteController::class, 'saveVault'])->middleware('throttle:5,1,put--vault');
    Route::get('/exports/quote-requests', [AdminQuoteRequestController::class, 'export'])->middleware('throttle:10,1,get--exports-quote-requests');
    Route::get('/newsletter', [\App\Http\Controllers\AdminNewsletterController::class, 'index']);
    Route::delete('/newsletter/{subscriber}', [\App\Http\Controllers\AdminNewsletterController::class, 'destroy']);
    Route::get('/exports/newsletter', [\App\Http\Controllers\AdminNewsletterController::class, 'export'])->middleware('throttle:10,1,get--exports-newsletter');
    Route::post('/quote-requests/{quoteRequest}/contacted', [AdminQuoteRequestController::class, 'markContacted']);
    Route::post('/quote-requests/{quoteRequest}/deposit-paid', [AdminQuoteRequestController::class, 'markDepositPaid'])->middleware('throttle:10,1,post--quote-requests-quoteRequest-deposit-paid');
    Route::apiResource('testimonials', AdminTestimonialController::class)->except('show');

    Route::get('/quote-requests', [AdminQuoteRequestController::class, 'index']);
    Route::get('/quote-requests/{quoteRequest}', [AdminQuoteRequestController::class, 'show']);
    Route::patch('/quote-requests/{quoteRequest}', [AdminQuoteRequestController::class, 'update']);
    Route::post('/quote-requests/{quoteRequest}/send-proposal', [AdminQuoteRequestController::class, 'sendProposal'])->middleware('throttle:10,1,post--quote-requests-quoteRequest-send-proposal');
    Route::post('/quote-requests/{quoteRequest}/rotate-client-access', [AdminQuoteRequestController::class, 'rotateClientAccess'])->middleware('throttle:5,1,post--quote-requests-quoteRequest-rotate-client-access');
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
