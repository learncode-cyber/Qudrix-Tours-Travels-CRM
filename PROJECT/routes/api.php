<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;

Route::prefix('v1')->group(function () {
    // Health check
    Route::get('/health', [HealthController::class, 'check']);

    // Auth routes (no JWT required)
    // FIX (Phase 15 security audit): these had ZERO rate limiting — a
    // brute-force login attack could try unlimited password guesses.
    // 5 attempts per minute per IP is a standard, reasonably strict
    // default for an auth endpoint.
    Route::middleware(['throttle:5,1'])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Protected routes (JWT required)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
    });
});

    // Phase 1: Customer Management (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // FIX (backlog item found 2026-09-20 building the Complaints
        // frontend): no staff-listing endpoint existed anywhere, so no
        // assignment UI (Complaints, Leads) could be built at all.
        Route::get('/users', 'App\\Http\\Controllers\\UserController@index');

        Route::apiResource('customers', 'App\\Http\\Controllers\\CustomerController');
        Route::post('/customers/{id}/family', 'App\\Http\\Controllers\\CustomerController@addFamily');
        Route::get('/customers/{id}/family', 'App\\Http\\Controllers\\CustomerController@getFamily');
        Route::get('/customers/{id}/timeline', 'App\\Http\\Controllers\\CustomerController@timeline');
    });

    // Phase 2: Tags (net new)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::get('/tags', 'App\\Http\\Controllers\\TagController@index');
        Route::post('/tags', 'App\\Http\\Controllers\\TagController@store');
        Route::delete('/tags/{id}', 'App\\Http\\Controllers\\TagController@delete');
        Route::post('/tags/attach', 'App\\Http\\Controllers\\TagController@attach');
        Route::post('/tags/detach', 'App\\Http\\Controllers\\TagController@detach');
    });

    // Phase 2: Custom Fields (net new)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::get('/custom-fields', 'App\\Http\\Controllers\\CustomFieldController@index');
        Route::post('/custom-fields', 'App\\Http\\Controllers\\CustomFieldController@store');
        Route::put('/custom-fields/{id}', 'App\\Http\\Controllers\\CustomFieldController@update');
        Route::delete('/custom-fields/{id}', 'App\\Http\\Controllers\\CustomFieldController@delete');
        Route::post('/custom-fields/values', 'App\\Http\\Controllers\\CustomFieldController@setValues');
        Route::get('/custom-fields/values/{entityType}/{entityId}', 'App\\Http\\Controllers\\CustomFieldController@getValues');
    });

    // Phase 1: Lead Management (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('leads', 'App\\Http\\Controllers\\LeadController')->only(['index', 'store', 'show']);
        Route::post('/leads/{id}/convert-to-customer', 'App\\Http\\Controllers\\LeadController@convertToCustomer');
        Route::put('/leads/{id}/status', 'App\\Http\\Controllers\\LeadController@updateStatus');
        Route::put('/leads/{id}/assign', 'App\\Http\\Controllers\\LeadController@assignLead');
        Route::post('/leads/{id}/score', 'App\\Http\\Controllers\\LeadController@scoreLeadForConversion');
        Route::post('/leads/{id}/follow-up', 'App\\Http\\Controllers\\LeadController@scheduleFollowUp');
        Route::get('/leads/pending/follow-ups', 'App\\Http\\Controllers\\LeadController@pendingFollowUps');
    });

    // Phase 1: Communication (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('communications', 'App\\Http\\Controllers\\CommunicationController')->only(['index', 'store']);
        Route::get('/customers/{customerId}/communications', 'App\\Http\\Controllers\\CommunicationController@getCustomerCommunications');
        Route::put('/communications/{id}/read', 'App\\Http\\Controllers\\CommunicationController@markAsRead');
        Route::get('/communications/stats', 'App\\Http\\Controllers\\CommunicationController@getCommunicationStats');
    });

    // Phase 1: Task Management (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // FIX (Phase 3 audit): /tasks/stats was shadowed by apiResource's
        // GET /tasks/{task} — same route-ordering bug as quotations/stats.
        Route::get('/tasks/stats', 'App\\Http\\Controllers\\TaskController@getTaskStats');
        Route::put('/tasks/{id}/complete', 'App\\Http\\Controllers\\TaskController@markComplete');
        Route::put('/tasks/{id}/incomplete', 'App\\Http\\Controllers\\TaskController@markIncomplete');
        Route::apiResource('tasks', 'App\\Http\\Controllers\\TaskController');
    });

    // Phase 2/3: Sales Pipeline, Quotations, Proposals, Invoices, Payments (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Quotation Management
        // FIX (Phase 3 audit): static routes (stats, send, versions,
        // approve/reject) MUST be registered before apiResource's
        // GET /quotations/{quotation}, or Laravel matches "stats" etc.
        // as the {quotation} route parameter and these are never reached.
        Route::get('/quotations/stats', 'App\\Http\\Controllers\\QuotationController@getQuotationStats');
        Route::post('/quotations/{id}/send', 'App\\Http\\Controllers\\QuotationController@sendQuotation');
        Route::post('/quotations/{id}/versions', 'App\\Http\\Controllers\\QuotationController@createVersion');
        Route::post('/quotations/{id}/approve', 'App\\Http\\Controllers\\QuotationController@approve');
        Route::post('/quotations/{id}/reject', 'App\\Http\\Controllers\\QuotationController@reject');
        Route::apiResource('quotations', 'App\\Http\\Controllers\\QuotationController')->except('destroy');

        // Proposal Management
        Route::get('/proposals/stats', 'App\\Http\\Controllers\\ProposalController@getProposalStats');
        Route::post('/proposals/from-quotation', 'App\\Http\\Controllers\\ProposalController@createFromQuotation');
        Route::post('/proposals/{id}/send', 'App\\Http\\Controllers\\ProposalController@sendProposal');
        Route::post('/proposals/{id}/sign', 'App\\Http\\Controllers\\ProposalController@signProposal');
        Route::post('/proposals/{id}/reject', 'App\\Http\\Controllers\\ProposalController@rejectProposal');
        Route::apiResource('proposals', 'App\\Http\\Controllers\\ProposalController')->only(['index', 'show']);

        // Invoice Management (Phase 3, net new)
        Route::get('/invoices/stats', 'App\\Http\\Controllers\\InvoiceController@getInvoiceStats');
        Route::post('/invoices/from-proposal', 'App\\Http\\Controllers\\InvoiceController@createFromProposal');
        Route::post('/invoices/{id}/void', 'App\\Http\\Controllers\\InvoiceController@void');
        Route::apiResource('invoices', 'App\\Http\\Controllers\\InvoiceController')->only(['index', 'show']);

        // Payment Management (Phase 3, net new)
        Route::get('/payments/stats', 'App\\Http\\Controllers\\PaymentController@getPaymentStats');
        Route::apiResource('payments', 'App\\Http\\Controllers\\PaymentController')->only(['index', 'store', 'show', 'update']);

        // Sales Pipeline
        Route::get('/pipeline/full', 'App\\Http\\Controllers\\PipelineController@getFullPipeline');
        Route::get('/pipeline/lead/{leadId}', 'App\\Http\\Controllers\\PipelineController@getLeadPipeline');
        Route::post('/pipeline/activity', 'App\\Http\\Controllers\\PipelineController@recordActivity');
        Route::put('/pipeline/stage', 'App\\Http\\Controllers\\PipelineController@updateLeadStage');
        Route::get('/pipeline/metrics', 'App\\Http\\Controllers\\PipelineController@getPipelineMetrics');
    });

    // Phase 3: Booking Engine (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Booking Management
        // FIX (Phase 3 audit): /bookings/stats was shadowed by apiResource's
        // GET /bookings/{booking} — same route-ordering bug as quotations/stats.
        Route::get('/bookings/stats', 'App\\Http\\Controllers\\BookingController@getBookingStats');
        Route::post('/bookings/from-proposal', 'App\\Http\\Controllers\\BookingController@createFromProposal');
        Route::post('/bookings/{id}/confirm', 'App\\Http\\Controllers\\BookingController@confirmBooking');
        Route::post('/bookings/{id}/cancel', 'App\\Http\\Controllers\\BookingController@cancelBooking');
        Route::apiResource('bookings', 'App\\Http\\Controllers\\BookingController');

        // Booking Travelers
        Route::post('/travelers/add', 'App\\Http\\Controllers\\TravelerController@addTraveler');
        Route::get('/bookings/{bookingId}/travelers', 'App\\Http\\Controllers\\TravelerController@getTravelers');
        Route::put('/travelers/{id}', 'App\\Http\\Controllers\\TravelerController@updateTraveler');
        Route::delete('/travelers/{id}', 'App\\Http\\Controllers\\TravelerController@removeTraveler');
        Route::get('/travelers/{id}/details', 'App\\Http\\Controllers\\TravelerController@getTravelerDetails');

        // Booking Itinerary
        Route::post('/itinerary/create', 'App\\Http\\Controllers\\ItineraryController@createItinerary');
        Route::get('/bookings/{bookingId}/itinerary', 'App\\Http\\Controllers\\ItineraryController@getItinerary');
        Route::put('/itinerary/{id}', 'App\\Http\\Controllers\\ItineraryController@updateItinerary');
        Route::delete('/itinerary/{id}', 'App\\Http\\Controllers\\ItineraryController@deleteItinerary');
        Route::get('/bookings/{bookingId}/itinerary/pdf', 'App\\Http\\Controllers\\ItineraryController@generateItineraryPdf');

        // Group Bookings
        Route::apiResource('groups', 'App\\Http\\Controllers\\GroupBookingController')->only(['index', 'store', 'show']);
        Route::post('/groups/{groupId}/bookings', 'App\\Http\\Controllers\\GroupBookingController@addBookingToGroup');
        Route::get('/groups/{groupId}/bookings', 'App\\Http\\Controllers\\GroupBookingController@getGroupBookings');
        Route::get('/groups/{groupId}/stats', 'App\\Http\\Controllers\\GroupBookingController@getGroupStats');
    });

    // Phase 4: Travel Management (Flights, Hotels, Transport, Destinations, Visa)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Flights
        Route::apiResource('flights', 'App\\Http\\Controllers\\FlightController');
        Route::post('/flights/book', 'App\\Http\\Controllers\\FlightController@bookFlight');

        // Hotels
        Route::apiResource('hotels', 'App\\Http\\Controllers\\HotelController');
        Route::post('/hotels/book', 'App\\Http\\Controllers\\HotelController@bookHotel');

        // Transport
        Route::apiResource('transports', 'App\\Http\\Controllers\\TransportController');
        Route::post('/transports/book', 'App\\Http\\Controllers\\TransportController@bookTransport');

        // Destinations
        Route::apiResource('destinations', 'App\\Http\\Controllers\\DestinationController');

        // Visas
        Route::apiResource('visas', 'App\\Http\\Controllers\\VisaController');
        Route::post('/visas/{id}/submit', 'App\\Http\\Controllers\\VisaController@submitApplication');
        Route::post('/visas/{id}/schedule-appointment', 'App\\Http\\Controllers\\VisaController@scheduleAppointment');
        Route::post('/visas/{id}/assign', 'App\\Http\\Controllers\\VisaController@assignStaff');
        Route::post('/visas/{id}/approve', 'App\\Http\\Controllers\\VisaController@approveVisa');
        Route::post('/visas/{id}/reject', 'App\\Http\\Controllers\\VisaController@rejectVisa');
        Route::get('/visas/booking/{bookingId}/status', 'App\\Http\\Controllers\\VisaController@getVisaStatus');
    });

    // Phase 5: Hajj/Umrah/Tours & Expense/Supplier/Complaint Management
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Hajj Packages
        // FIX (Phase 5 audit): 'stats' registered before apiResource's
        // GET /hajj/{hajj} to avoid the route-shadowing bug found and
        // fixed in Phase 3/4.
        Route::get('/hajj/stats', 'App\\Http\\Controllers\\HajjController@getPackageStats');
        Route::apiResource('hajj', 'App\\Http\\Controllers\\HajjController')->only(['index', 'store', 'show', 'update']);

        // Umrah Packages
        Route::get('/umrah/stats', 'App\\Http\\Controllers\\UmrahController@getPackageStats');
        Route::apiResource('umrah', 'App\\Http\\Controllers\\UmrahController')->only(['index', 'store', 'show', 'update']);

        // Ritual Checkpoints (Phase 5, net new controller for existing model)
        Route::get('/bookings/{bookingId}/rituals', 'App\\Http\\Controllers\\RitualCheckpointController@index');
        Route::post('/bookings/{bookingId}/rituals', 'App\\Http\\Controllers\\RitualCheckpointController@store');
        Route::put('/rituals/{id}/status', 'App\\Http\\Controllers\\RitualCheckpointController@updateStatus');

        // FIX (MASTER_PROJECT_AUDIT.md P1): Mahram compliance tracking for
        // Hajj/Umrah travelers — previously nonexistent.
        Route::get('/bookings/{bookingId}/mahrams', 'App\\Http\\Controllers\\MahramController@indexForBooking');
        Route::post('/mahrams', 'App\\Http\\Controllers\\MahramController@store');
        Route::post('/mahrams/{id}/verify', 'App\\Http\\Controllers\\MahramController@verify');
        Route::delete('/mahrams/{id}', 'App\\Http\\Controllers\\MahramController@destroy');

        // FIX (MASTER_PROJECT_AUDIT.md P1): per-room traveler assignment
        // for hotel bookings — previously only an aggregate room count existed.
        Route::get('/hotel-bookings/{hotelBookingId}/rooms', 'App\\Http\\Controllers\\RoomAssignmentController@indexForHotelBooking');
        Route::post('/room-assignments', 'App\\Http\\Controllers\\RoomAssignmentController@store');
        Route::post('/room-assignments/{id}/travelers', 'App\\Http\\Controllers\\RoomAssignmentController@assignTravelers');
        Route::delete('/room-assignments/{id}', 'App\\Http\\Controllers\\RoomAssignmentController@destroy');

        // FIX (MASTER_PROJECT_AUDIT.md P1): installment payment plans —
        // previously no way to split a booking's cost over time at all.
        Route::get('/bookings/{bookingId}/installment-plans', 'App\\Http\\Controllers\\InstallmentPlanController@indexForBooking');
        Route::post('/installment-plans', 'App\\Http\\Controllers\\InstallmentPlanController@store');
        Route::post('/installments/{installmentId}/pay', 'App\\Http\\Controllers\\InstallmentPlanController@recordPayment');
        Route::post('/installments/flag-overdue', 'App\\Http\\Controllers\\InstallmentPlanController@flagOverdue');

        // Document Checklist (Phase 5, net new - shared by Hajj/Umrah/Student Visa)
        Route::get('/document-requirements', 'App\\Http\\Controllers\\DocumentChecklistController@indexRequirements');
        Route::post('/document-requirements', 'App\\Http\\Controllers\\DocumentChecklistController@storeRequirement');
        Route::get('/document-readiness/{entityType}/{entityId}', 'App\\Http\\Controllers\\DocumentChecklistController@readiness');
        Route::post('/document-submissions', 'App\\Http\\Controllers\\DocumentChecklistController@submit');
        Route::post('/document-submissions/{id}/verify', 'App\\Http\\Controllers\\DocumentChecklistController@verify');
        Route::post('/document-submissions/{id}/reject', 'App\\Http\\Controllers\\DocumentChecklistController@reject');

        // Student Visa (Phase 5, net new)
        Route::get('/student-visas/stats', 'App\\Http\\Controllers\\StudentVisaController@getStats');
        Route::post('/student-visas/{id}/assign', 'App\\Http\\Controllers\\StudentVisaController@assignCounselor');
        Route::post('/student-visas/{id}/advance', 'App\\Http\\Controllers\\StudentVisaController@advanceStatus');
        Route::apiResource('student-visas', 'App\\Http\\Controllers\\StudentVisaController')->except(['destroy']);
        
        // Tour Packages
        Route::apiResource('tours', 'App\\Http\\Controllers\\TourController')->only(['index', 'store', 'show', 'update']);
        
        // Expenses
        Route::post('/expenses', 'App\\Http\\Controllers\\ExpenseController@create');
        Route::get('/bookings/{bookingId}/expenses', 'App\\Http\\Controllers\\ExpenseController@getByBooking');
        
        // Suppliers
        Route::apiResource('suppliers', 'App\\Http\\Controllers\\SupplierController');

        // FIX (MASTER_PROJECT_AUDIT.md P0): Agent and Vendor are distinct
        // business relationships from Supplier and had no entity/routes at
        // all before this pass.
        Route::get('/agents/{id}/performance', 'App\\Http\\Controllers\\AgentController@performance');
        Route::get('/agents/{id}/commission-ledger', 'App\\Http\\Controllers\\AgentController@commissionLedger');
        Route::post('/agents/{id}/pay-commission', 'App\\Http\\Controllers\\AgentController@payCommission');
        Route::apiResource('agents', 'App\\Http\\Controllers\\AgentController');
        Route::apiResource('vendors', 'App\\Http\\Controllers\\VendorController');
        
        // Complaints
        Route::get('/complaints/sla-check', 'App\\Http\\Controllers\\ComplaintController@checkSlaBreaches');
        Route::get('/complaints', 'App\\Http\\Controllers\\ComplaintController@index');
        Route::post('/complaints', 'App\\Http\\Controllers\\ComplaintController@create');
        Route::get('/complaints/{id}', 'App\\Http\\Controllers\\ComplaintController@show');
        Route::put('/complaints/{id}/status', 'App\\Http\\Controllers\\ComplaintController@updateStatus');
        Route::post('/complaints/{id}/assign', 'App\\Http\\Controllers\\ComplaintController@assign');
        Route::post('/complaints/{id}/resolve', 'App\\Http\\Controllers\\ComplaintController@resolve');
        Route::post('/complaints/{id}/approve-compensation', 'App\\Http\\Controllers\\ComplaintController@approveCompensation');
        Route::post('/complaints/{id}/reject-compensation', 'App\\Http\\Controllers\\ComplaintController@rejectCompensation');
        Route::post('/complaints/{id}/suggest-response', 'App\\Http\\Controllers\\ComplaintController@suggestResponse');
    });

    // Phase 6: Automation Engine + Templates + Dashboard
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Automation Management
        Route::apiResource('automations', 'App\\Http\\Controllers\\AutomationController')->only(['index', 'store', 'show', 'update']);
        Route::post('/automations/{id}/execute', 'App\\Http\\Controllers\\AutomationController@execute');
        Route::post('/automations/{id}/test', 'App\\Http\\Controllers\\AutomationController@test');

        // FIX (backlog item found 2026-09-21 building the Automation
        // frontend): no route anywhere let a caller add/edit/remove the
        // steps that define what an automation actually does.
        Route::post('/automations/{automationId}/steps', 'App\\Http\\Controllers\\AutomationStepController@store');
        Route::put('/automations/{automationId}/steps/{stepId}', 'App\\Http\\Controllers\\AutomationStepController@update');
        Route::delete('/automations/{automationId}/steps/{stepId}', 'App\\Http\\Controllers\\AutomationStepController@destroy');
        
        // Automation Templates
        Route::get('/automation-templates', 'App\\Http\\Controllers\\AutomationTemplateController@index');
        Route::get('/automation-templates/{id}', 'App\\Http\\Controllers\\AutomationTemplateController@show');
        Route::get('/automation-templates/category/{category}', 'App\\Http\\Controllers\\AutomationTemplateController@getByCategory');
        Route::post('/automation-templates/{id}/use', 'App\\Http\\Controllers\\AutomationTemplateController@useTemplate');
        
        // Automation Logs
        Route::get('/automations/{automationId}/logs', 'App\\Http\\Controllers\\AutomationLogController@getAutomationLogs');
        Route::get('/automations/{automationId}/stats', 'App\\Http\\Controllers\\AutomationLogController@getStats');
        Route::delete('/automations/{automationId}/logs', 'App\\Http\\Controllers\\AutomationLogController@clearLogs');
        
        // Automation Dashboard
        Route::get('/automation-dashboard/summary', 'App\\Http\\Controllers\\AutomationDashboardController@getSummary');
        Route::get('/automation-dashboard/metrics', 'App\\Http\\Controllers\\AutomationDashboardController@getMetrics');
    });

    // Phase 7: AI & Analytics + Reports + Insights + Segmentation + Predictions
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Analytics
        Route::get('/analytics/lead-funnel', 'App\\Http\\Controllers\\AnalyticsController@leadFunnel');
        Route::get('/analytics/conversion-rate', 'App\\Http\\Controllers\\AnalyticsController@conversionRate');
        Route::get('/analytics/deal-value', 'App\\Http\\Controllers\\AnalyticsController@dealValue');
        Route::get('/analytics/sales-performance', 'App\\Http\\Controllers\\AnalyticsController@salesPerformance');
        Route::get('/analytics/revenue', 'App\\Http\\Controllers\\AnalyticsController@revenueAnalytics');
        Route::get('/analytics/revenue-forecast', 'App\\Http\\Controllers\\AnalyticsController@revenueForecast');
        Route::get('/analytics/metrics', 'App\\Http\\Controllers\\AnalyticsController@getMetrics');
        Route::get('/analytics/metric/{type}', 'App\\Http\\Controllers\\AnalyticsController@getMetricByType');
        
        // Reports
        Route::get('/reports', 'App\\Http\\Controllers\\ReportController@index');
        Route::post('/reports', 'App\\Http\\Controllers\\ReportController@create');
        Route::post('/reports/{id}/generate', 'App\\Http\\Controllers\\ReportController@generate');
        Route::post('/reports/{id}/schedule', 'App\\Http\\Controllers\\ReportController@schedule');
        
        // Insights
        Route::get('/customers/{customerId}/churn-risk', 'App\\Http\\Controllers\\PredictionController@churnRisk');
        Route::get('/customers/{customerId}/predicted-next-booking-value', 'App\\Http\\Controllers\\PredictionController@nextBookingValue');
        Route::get('/predictions/popular-destination', 'App\\Http\\Controllers\\PredictionController@popularDestination');
        Route::post('/insights/generate', 'App\\Http\\Controllers\\InsightController@generate');
        Route::get('/insights', 'App\\Http\\Controllers\\InsightController@list');
        Route::get('/insights/type/{type}', 'App\\Http\\Controllers\\InsightController@getByType');
        Route::get('/insights/trending', 'App\\Http\\Controllers\\InsightController@getTrending');
        
        // Customer Segments
        Route::get('/segments', 'App\\Http\\Controllers\\SegmentController@list');
        Route::post('/segments', 'App\\Http\\Controllers\\SegmentController@create');
        Route::get('/segments/{id}/members', 'App\\Http\\Controllers\\SegmentController@getMembers');
        
        // Dashboard
        Route::get('/dashboard/default', 'App\\Http\\Controllers\\DashboardController@getDefault');
        Route::put('/dashboard/{id}', 'App\\Http\\Controllers\\DashboardController@update');
        Route::get('/dashboard/kpi', 'App\\Http\\Controllers\\DashboardController@getKPI');
    });

    // Phase 8: Offline & PWA + Sync Engine + Cache Management
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Sync & Offline
        Route::post('/sync', 'App\\Http\\Controllers\\SyncController@syncData');
        Route::get('/sync/pending', 'App\\Http\\Controllers\\SyncController@getPendingSync');
        Route::get('/sync/status/{batchId}', 'App\\Http\\Controllers\\SyncController@getSyncStatus');
        Route::post('/sync/retry-failed', 'App\\Http\\Controllers\\SyncController@resyncFailed');
        
        // Cache Management
        Route::get('/cache/policies', 'App\\Http\\Controllers\\CacheController@getCachePolicies');
        Route::post('/cache/policies', 'App\\Http\\Controllers\\CacheController@createPolicy');
        Route::post('/cache/clear', 'App\\Http\\Controllers\\CacheController@clearCache');
        Route::get('/cache/stats', 'App\\Http\\Controllers\\CacheController@getCacheStats');
        
        // PWA Configuration
        Route::get('/pwa/manifest.json', 'App\\Http\\Controllers\\PWAController@getManifest');
        Route::put('/pwa/settings', 'App\\Http\\Controllers\\PWAController@updateSettings');
        Route::get('/sw.js', 'App\\Http\\Controllers\\PWAController@getServiceWorker');
        
        // Offline Data
        Route::get('/offline/data', 'App\\Http\\Controllers\\OfflineController@downloadOfflineData');
        Route::get('/offline/status', 'App\\Http\\Controllers\\OfflineController@getOfflineStatus');
        Route::post('/offline/sync', 'App\\Http\\Controllers\\OfflineController@syncOfflineChanges');
        Route::post('/offline/clear', 'App\\Http\\Controllers\\OfflineController@clearOfflineData');
    });

    // Phase 11: Sales Strategies + AI Copilot (net new)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::get('/sales-strategy', 'App\\Http\\Controllers\\SalesCopilotController@getStrategy');
        Route::post('/sales-strategy', 'App\\Http\\Controllers\\SalesCopilotController@setStrategy');

        Route::get('/sales-scripts', 'App\\Http\\Controllers\\SalesCopilotController@indexScripts');
        Route::post('/sales-scripts', 'App\\Http\\Controllers\\SalesCopilotController@storeScript');
        Route::delete('/sales-scripts/{id}', 'App\\Http\\Controllers\\SalesCopilotController@deleteScript');

        Route::get('/objection-responses', 'App\\Http\\Controllers\\SalesCopilotController@indexObjections');
        Route::post('/objection-responses', 'App\\Http\\Controllers\\SalesCopilotController@storeObjection');
        Route::delete('/objection-responses/{id}', 'App\\Http\\Controllers\\SalesCopilotController@deleteObjection');

        Route::get('/pipeline-risk', 'App\\Http\\Controllers\\SalesCopilotController@pipelineRisk');
        Route::get('/leads/{leadId}/deal-risk', 'App\\Http\\Controllers\\SalesCopilotController@dealRisk');
        Route::get('/leads/{leadId}/next-best-action', 'App\\Http\\Controllers\\SalesCopilotController@nextBestAction');
        Route::post('/conversations/{conversationId}/suggest-reply', 'App\\Http\\Controllers\\SalesCopilotController@suggestReply');
    });

    // Phase 10: AI Sales Agent (net new)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::get('/conversations', 'App\\Http\\Controllers\\ConversationController@index');
        Route::post('/conversations', 'App\\Http\\Controllers\\ConversationController@start');
        Route::get('/conversations/{id}', 'App\\Http\\Controllers\\ConversationController@show');
        Route::post('/conversations/{id}/messages', 'App\\Http\\Controllers\\ConversationController@sendMessage');
        Route::post('/conversations/{id}/escalate', 'App\\Http\\Controllers\\ConversationController@escalate');
        Route::post('/conversations/{id}/assign', 'App\\Http\\Controllers\\ConversationController@assign');
        Route::post('/conversations/{id}/close', 'App\\Http\\Controllers\\ConversationController@close');
    });

    // Phase 13: Upsell/Cross-sell + A/B Testing (net new)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::get('/upsell-offers', 'App\\Http\\Controllers\\UpsellController@index');
        Route::post('/upsell-offers', 'App\\Http\\Controllers\\UpsellController@store');
        Route::put('/upsell-offers/{id}', 'App\\Http\\Controllers\\UpsellController@update');
        Route::delete('/upsell-offers/{id}', 'App\\Http\\Controllers\\UpsellController@delete');
        Route::get('/bookings/{bookingId}/upsell-recommendations', 'App\\Http\\Controllers\\UpsellController@forBooking');
        Route::get('/customers/{customerId}/cross-sell-recommendations', 'App\\Http\\Controllers\\UpsellController@crossSellForCustomer');

        Route::get('/experiments', 'App\\Http\\Controllers\\ExperimentController@index');
        Route::post('/experiments', 'App\\Http\\Controllers\\ExperimentController@store');
        Route::post('/experiments/{id}/start', 'App\\Http\\Controllers\\ExperimentController@start');
        Route::get('/experiments/{id}/results', 'App\\Http\\Controllers\\ExperimentController@getResults');
        Route::post('/experiment-variants/{variantId}/track', 'App\\Http\\Controllers\\ExperimentController@trackEvent');
    });

    // Phase 16: SEO metadata management + campaign attribution (net new)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::get('/seo-metadata', 'App\\Http\\Controllers\\SeoController@index');
        Route::post('/seo-metadata', 'App\\Http\\Controllers\\SeoController@upsert');
        Route::get('/analytics/campaign-attribution', 'App\\Http\\Controllers\\SeoController@campaignAttribution');
    });

    // FIX (MASTER_PROJECT_AUDIT.md P2): Meta Pixel/Conversions API + GA4
    // config and CRM funnel-event logging — previously nonexistent.
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::get('/tracking-config', 'App\\Http\\Controllers\\TrackingConfigController@show');
        Route::put('/tracking-config', 'App\\Http\\Controllers\\TrackingConfigController@upsert');
        Route::get('/conversion-events', 'App\\Http\\Controllers\\ConversionEventController@index');
        Route::post('/conversion-events', 'App\\Http\\Controllers\\ConversionEventController@store');
    });

    // Phase 18: Multi-tenant SaaS readiness (net new)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::get('/tenant', 'App\\Http\\Controllers\\TenantController@show');
        Route::put('/tenant', 'App\\Http\\Controllers\\TenantController@update');
        Route::get('/subscription-plans', 'App\\Http\\Controllers\\TenantController@plans');
    });

    // FIX (Phase 18 verification): App\Http\Controllers\Api\ApiKeyController
    // (tenant self-service API key management) was fully implemented, and its
    // policy was registered and fixed back in the Phase 15 security audit,
    // but no route anywhere ever pointed to it — the feature was completely
    // unreachable for every tenant. /logs and /stats are registered before
    // the /{id} routes so they aren't swallowed by the {apiKey} wildcard.
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->prefix('api-keys')->group(function () {
        Route::get('/', 'App\\Http\\Controllers\\Api\\ApiKeyController@index');
        Route::post('/', 'App\\Http\\Controllers\\Api\\ApiKeyController@store');
        Route::get('/logs', 'App\\Http\\Controllers\\Api\\ApiKeyController@logs');
        Route::get('/stats', 'App\\Http\\Controllers\\Api\\ApiKeyController@stats');
        Route::get('/{apiKey}', 'App\\Http\\Controllers\\Api\\ApiKeyController@show');
        Route::patch('/{apiKey}', 'App\\Http\\Controllers\\Api\\ApiKeyController@update');
        Route::post('/{apiKey}/revoke', 'App\\Http\\Controllers\\Api\\ApiKeyController@revoke');
        Route::delete('/{apiKey}', 'App\\Http\\Controllers\\Api\\ApiKeyController@destroy');
    });

    // Phase 9: AI Provider Management (net new)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::get('/ai-providers', 'App\\Http\\Controllers\\AIProviderController@index');
        Route::post('/ai-providers', 'App\\Http\\Controllers\\AIProviderController@store');
        Route::put('/ai-providers/{id}', 'App\\Http\\Controllers\\AIProviderController@update');
        Route::delete('/ai-providers/{id}', 'App\\Http\\Controllers\\AIProviderController@delete');
        Route::post('/ai-providers/{id}/test', 'App\\Http\\Controllers\\AIProviderController@testConnection');
        Route::get('/ai-providers/usage-stats', 'App\\Http\\Controllers\\AIProviderController@getUsageStats');
        Route::post('/ai-feature-config', 'App\\Http\\Controllers\\AIProviderController@setFeatureConfig');
    });

    // Phase 19: Billing / Subscription (basic structure, no real payment integration)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Subscription management
        Route::get('/subscriptions/current', 'App\\Http\\Controllers\\SubscriptionController@current');
        Route::post('/subscriptions', 'App\\Http\\Controllers\\SubscriptionController@subscribe');
        Route::post('/subscriptions/cancel', 'App\\Http\\Controllers\\SubscriptionController@cancel');
        Route::get('/subscriptions/usage', 'App\\Http\\Controllers\\SubscriptionController@usage');
        // Plans (admin)
        Route::middleware('role:admin')->group(function () {
            Route::get('/subscription-plans', 'App\\Http\\Controllers\\SubscriptionPlanController@index');
            Route::post('/subscription-plans', 'App\\Http\\Controllers\\SubscriptionPlanController@store');
            Route::put('/subscription-plans/{id}', 'App\\Http\\Controllers\\SubscriptionPlanController@update');
            Route::delete('/subscription-plans/{id}', 'App\\Http\\Controllers\\SubscriptionPlanController@delete');
        });
    });

    // Phase 9: Production Hardening & Deployment
    Route::middleware(['jwt.auth', 'security.headers', 'rate.limit'])->group(function () {
        Route::get('/health', 'App\\Http\\Controllers\\HealthController@status');
        Route::get('/health/detailed', 'App\\Http\\Controllers\\HealthController@detailed');
    });
    

    // Settings (net new - previously used by Phase 3 approval threshold
    // and Phase 7 channel credentials with no way to actually set them)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::get('/settings', 'App\\Http\\Controllers\\SettingController@index');
        Route::post('/settings', 'App\\Http\\Controllers\\SettingController@store');
        Route::delete('/settings/{key}', 'App\\Http\\Controllers\\SettingController@delete');
    });

    // Phase 7: Communication + Notifications (net new)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::get('/notifications', 'App\\Http\\Controllers\\NotificationController@index');
        Route::put('/notifications/{id}/read', 'App\\Http\\Controllers\\NotificationController@markAsRead');
        Route::post('/notifications/sweep/payment-reminders', 'App\\Http\\Controllers\\NotificationController@sendPaymentReminders');
        Route::post('/notifications/sweep/follow-up-reminders', 'App\\Http\\Controllers\\NotificationController@sendFollowUpReminders');

        Route::get('/notification-templates', 'App\\Http\\Controllers\\NotificationTemplateController@index');
        Route::post('/notification-templates', 'App\\Http\\Controllers\\NotificationTemplateController@store');
        Route::delete('/notification-templates/{id}', 'App\\Http\\Controllers\\NotificationTemplateController@delete');
    });

    // Phase 6: Pricing Engine + Custom Package Builder (net new)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::get('/pricing-rules', 'App\\Http\\Controllers\\PricingRuleController@index');
        Route::post('/pricing-rules', 'App\\Http\\Controllers\\PricingRuleController@store');
        Route::put('/pricing-rules/{id}', 'App\\Http\\Controllers\\PricingRuleController@update');
        Route::delete('/pricing-rules/{id}', 'App\\Http\\Controllers\\PricingRuleController@delete');

        Route::post('/package-builder/recommend', 'App\\Http\\Controllers\\PackageBuilderController@recommend');
        Route::post('/package-builder/quotation', 'App\\Http\\Controllers\\PackageBuilderController@buildQuotation');
    });

    // Admin endpoints (require super-admin role)
    Route::middleware(['jwt.auth', 'security.headers'])->group(function () {
        Route::post('/admin/optimize-db', 'App\\Http\\Controllers\\AdminController@optimizeDatabase');
        Route::post('/admin/analyze-db', 'App\\Http\\Controllers\\AdminController@analyzeDatabase');
        Route::post('/admin/backup', 'App\\Http\\Controllers\\AdminController@createBackup');
        Route::get('/admin/backups', 'App\\Http\\Controllers\\AdminController@listBackups');
    });
