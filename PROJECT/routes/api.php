<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;

Route::prefix('v1')->group(function () {
    // Health check
    Route::get('/health', [HealthController::class, 'check']);

    // Auth routes (no JWT required)
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes (JWT required)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
    });

    // Phase 1: Customer Management (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('customers', '\App\Http\Controllers\CustomerController');
        Route::post('/customers/{id}/family', '\App\Http\Controllers\CustomerController@addFamily');
        Route::get('/customers/{id}/family', '\App\Http\Controllers\CustomerController@getFamily');
    });

    // Phase 1: Lead Management (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('leads', '\App\Http\Controllers\LeadController')->only(['index', 'store', 'show']);
        Route::put('/leads/{id}/status', '\App\Http\Controllers\LeadController@updateStatus');
        Route::put('/leads/{id}/assign', '\App\Http\Controllers\LeadController@assignLead');
        Route::post('/leads/{id}/score', '\App\Http\Controllers\LeadController@scoreLeadForConversion');
        Route::post('/leads/{id}/follow-up', '\App\Http\Controllers\LeadController@scheduleFollowUp');
        Route::get('/leads/pending/follow-ups', '\App\Http\Controllers\LeadController@pendingFollowUps');
    });

    // Phase 1: Communication (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('communications', '\App\Http\Controllers\CommunicationController')->only(['index', 'store']);
        Route::get('/customers/{customerId}/communications', '\App\Http\Controllers\CommunicationController@getCustomerCommunications');
        Route::put('/communications/{id}/read', '\App\Http\Controllers\CommunicationController@markAsRead');
        Route::get('/communications/stats', '\App\Http\Controllers\CommunicationController@getCommunicationStats');
    });

    // Phase 1: Task Management (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('tasks', '\App\Http\Controllers\TaskController');
        Route::put('/tasks/{id}/complete', '\App\Http\Controllers\TaskController@markComplete');
        Route::put('/tasks/{id}/incomplete', '\App\Http\Controllers\TaskController@markIncomplete');
        Route::get('/tasks/stats', '\App\Http\Controllers\TaskController@getTaskStats');
    });

    // Phase 2: Sales Pipeline & Quotations (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Quotation Management
        Route::apiResource('quotations', '\App\Http\Controllers\QuotationController')->except('destroy');
        Route::post('/quotations/{id}/send', '\App\Http\Controllers\QuotationController@sendQuotation');
        Route::get('/quotations/stats', '\App\Http\Controllers\QuotationController@getQuotationStats');

        // Proposal Management
        Route::apiResource('proposals', '\App\Http\Controllers\ProposalController')->only(['index', 'show']);
        Route::post('/proposals/from-quotation', '\App\Http\Controllers\ProposalController@createFromQuotation');
        Route::post('/proposals/{id}/send', '\App\Http\Controllers\ProposalController@sendProposal');
        Route::post('/proposals/{id}/sign', '\App\Http\Controllers\ProposalController@signProposal');
        Route::post('/proposals/{id}/reject', '\App\Http\Controllers\ProposalController@rejectProposal');
        Route::get('/proposals/stats', '\App\Http\Controllers\ProposalController@getProposalStats');

        // Sales Pipeline
        Route::get('/pipeline/full', '\App\Http\Controllers\PipelineController@getFullPipeline');
        Route::get('/pipeline/lead/{leadId}', '\App\Http\Controllers\PipelineController@getLeadPipeline');
        Route::post('/pipeline/activity', '\App\Http\Controllers\PipelineController@recordActivity');
        Route::put('/pipeline/stage', '\App\Http\Controllers\PipelineController@updateLeadStage');
        Route::get('/pipeline/metrics', '\App\Http\Controllers\PipelineController@getPipelineMetrics');

        // Quotation approval workflow + versioning
        Route::post('/quotations/{id}/submit-for-approval', '\App\Http\Controllers\QuotationController@submitForApproval');
        Route::post('/quotations/{id}/approve', '\App\Http\Controllers\QuotationController@approve');
        Route::post('/quotations/{id}/new-version', '\App\Http\Controllers\QuotationController@newVersion');
        Route::get('/quotations/{id}/pdf', '\App\Http\Controllers\QuotationPdfController@download');

        // Quote Templates
        Route::apiResource('quotation-templates', '\App\Http\Controllers\QuotationTemplateController')->only(['index', 'store', 'show', 'update', 'destroy']);

        // Invoices
        Route::apiResource('invoices', '\App\Http\Controllers\InvoiceController')->only(['index', 'store', 'show']);
        Route::post('/invoices/{id}/payments', '\App\Http\Controllers\InvoiceController@recordPayment');
        Route::post('/invoices/{id}/send', '\App\Http\Controllers\InvoiceController@send');
        Route::get('/invoices/stats', '\App\Http\Controllers\InvoiceController@stats');
    });

    // Public, token-authenticated quotation sharing — no JWT session.
    // Rate-limited since it's an unauthenticated surface.
    Route::prefix('public')->middleware(['rate.limit'])->group(function () {
        Route::get('/quotations/{token}', '\App\Http\Controllers\QuotationShareController@show');
        Route::post('/quotations/{token}/accept', '\App\Http\Controllers\QuotationShareController@accept');
        Route::post('/quotations/{token}/reject', '\App\Http\Controllers\QuotationShareController@reject');
    });

    // Phase 3: Booking Engine (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Booking Management
        Route::apiResource('bookings', '\App\Http\Controllers\BookingController');
        Route::post('/bookings/{id}/confirm', '\App\Http\Controllers\BookingController@confirmBooking');
        Route::post('/bookings/{id}/cancel', '\App\Http\Controllers\BookingController@cancelBooking');
        Route::get('/bookings/stats', '\App\Http\Controllers\BookingController@getBookingStats');

        // Booking Travelers
        Route::post('/travelers/add', '\App\Http\Controllers\TravelerController@addTraveler');
        Route::get('/bookings/{bookingId}/travelers', '\App\Http\Controllers\TravelerController@getTravelers');
        Route::put('/travelers/{id}', '\App\Http\Controllers\TravelerController@updateTraveler');
        Route::delete('/travelers/{id}', '\App\Http\Controllers\TravelerController@removeTraveler');
        Route::get('/travelers/{id}/details', '\App\Http\Controllers\TravelerController@getTravelerDetails');

        // Booking Itinerary
        Route::post('/itinerary/create', '\App\Http\Controllers\ItineraryController@createItinerary');
        Route::get('/bookings/{bookingId}/itinerary', '\App\Http\Controllers\ItineraryController@getItinerary');
        Route::put('/itinerary/{id}', '\App\Http\Controllers\ItineraryController@updateItinerary');
        Route::delete('/itinerary/{id}', '\App\Http\Controllers\ItineraryController@deleteItinerary');
        Route::get('/bookings/{bookingId}/itinerary/pdf', '\App\Http\Controllers\ItineraryController@generateItineraryPdf');

        // Group Bookings
        Route::apiResource('groups', '\App\Http\Controllers\GroupBookingController')->only(['index', 'store', 'show']);
        Route::post('/groups/{groupId}/bookings', '\App\Http\Controllers\GroupBookingController@addBookingToGroup');
        Route::get('/groups/{groupId}/bookings', '\App\Http\Controllers\GroupBookingController@getGroupBookings');
        Route::get('/groups/{groupId}/stats', '\App\Http\Controllers\GroupBookingController@getGroupStats');
    });

    // Phase 4: Travel Management (Flights, Hotels, Transport, Destinations, Visa)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Flights
        Route::apiResource('flights', '\App\Http\Controllers\FlightController');
        Route::post('/flights/book', '\App\Http\Controllers\FlightController@bookFlight');
        Route::post('/flight-bookings/{id}/cancel', '\App\Http\Controllers\FlightController@cancelFlightBooking');

        // Hotels
        Route::apiResource('hotels', '\App\Http\Controllers\HotelController');
        Route::post('/hotels/book', '\App\Http\Controllers\HotelController@bookHotel');
        Route::get('/hotels/{hotelId}/room-types', '\App\Http\Controllers\HotelRoomTypeController@index');
        Route::post('/hotels/{hotelId}/room-types', '\App\Http\Controllers\HotelRoomTypeController@store');
        Route::put('/hotels/{hotelId}/room-types/{id}', '\App\Http\Controllers\HotelRoomTypeController@update');
        Route::delete('/hotels/{hotelId}/room-types/{id}', '\App\Http\Controllers\HotelRoomTypeController@destroy');
        Route::get('/hotels/{hotelId}/extra-services', '\App\Http\Controllers\HotelExtraServiceController@index');
        Route::post('/hotels/{hotelId}/extra-services', '\App\Http\Controllers\HotelExtraServiceController@store');
        Route::delete('/hotels/{hotelId}/extra-services/{id}', '\App\Http\Controllers\HotelExtraServiceController@destroy');

        // Transport
        Route::apiResource('transports', '\App\Http\Controllers\TransportController');
        Route::post('/transports/book', '\App\Http\Controllers\TransportController@bookTransport');

        // Destinations
        Route::apiResource('destinations', '\App\Http\Controllers\DestinationController');

        // Visas
        Route::apiResource('visas', '\App\Http\Controllers\VisaController');
        Route::post('/visas/{id}/submit', '\App\Http\Controllers\VisaController@submitApplication');
        Route::post('/visas/{id}/approve', '\App\Http\Controllers\VisaController@approveVisa');
        Route::post('/visas/{id}/assign', '\App\Http\Controllers\VisaController@assign');
        Route::get('/visas/{id}/checklist', '\App\Http\Controllers\VisaController@checklist');
        Route::put('/visas/{id}/checklist/{itemId}', '\App\Http\Controllers\VisaController@updateChecklistItem');
        Route::get('/visas/booking/{bookingId}/status', '\App\Http\Controllers\VisaController@getVisaStatus');

        // Visa document requirements (admin-configurable per country/type)
        Route::get('/visa-document-requirements', '\App\Http\Controllers\VisaDocumentRequirementController@index');
        Route::post('/visa-document-requirements', '\App\Http\Controllers\VisaDocumentRequirementController@store');
        Route::delete('/visa-document-requirements/{id}', '\App\Http\Controllers\VisaDocumentRequirementController@destroy');
    });

    // Phase 5: Hajj/Umrah/Tours & Expense/Supplier/Complaint Management
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Hajj Packages
        Route::apiResource('hajj', '\App\Http\Controllers\HajjController')->only(['index', 'store', 'show', 'update']);
        
        // Umrah Packages
        Route::apiResource('umrah', '\App\Http\Controllers\UmrahController')->only(['index', 'store', 'show']);

        // Hajj/Umrah Groups (departure management) + Pilgrims
        Route::apiResource('hajj-umrah-groups', '\App\Http\Controllers\HajjUmrahGroupController')->only(['index', 'store', 'show', 'update']);
        Route::get('/hajj-umrah-groups/{id}/report', '\App\Http\Controllers\HajjUmrahGroupController@report');

        Route::apiResource('pilgrims', '\App\Http\Controllers\PilgrimController')->only(['index', 'store', 'show', 'update']);
        Route::put('/pilgrims/{id}/room', '\App\Http\Controllers\PilgrimController@assignRoom');
        Route::put('/pilgrims/{id}/transport', '\App\Http\Controllers\PilgrimController@assignTransport');
        Route::post('/pilgrims/{id}/payments', '\App\Http\Controllers\PilgrimController@recordPayment');

        // Student Visa
        Route::apiResource('student-visa-applications', '\App\Http\Controllers\StudentVisaController')->only(['index', 'store', 'show', 'update']);
        Route::put('/student-visa-applications/{id}/status', '\App\Http\Controllers\StudentVisaController@updateStatus');
        Route::post('/student-visa-applications/{id}/offer-letter', '\App\Http\Controllers\StudentVisaController@recordOfferLetter');
        Route::post('/student-visa-applications/{id}/embassy-appointment', '\App\Http\Controllers\StudentVisaController@scheduleEmbassyAppointment');
        Route::put('/student-visa-applications/{id}/visa-status', '\App\Http\Controllers\StudentVisaController@updateVisaStatus');
        Route::post('/student-visa-applications/{id}/assign-counsellor', '\App\Http\Controllers\StudentVisaController@assignCounsellor');

        // Tour Packages
        Route::apiResource('tours', '\App\Http\Controllers\TourController')->only(['index', 'store', 'show', 'update']);
        
        // Expenses
        Route::post('/expenses', '\App\Http\Controllers\ExpenseController@create');
        Route::get('/bookings/{bookingId}/expenses', '\App\Http\Controllers\ExpenseController@getByBooking');
        
        // Suppliers
        Route::apiResource('suppliers', '\App\Http\Controllers\SupplierController');
        
        // Complaints
        Route::get('/complaints', '\App\Http\Controllers\ComplaintController@index');
        Route::post('/complaints', '\App\Http\Controllers\ComplaintController@create');
        Route::put('/complaints/{id}/status', '\App\Http\Controllers\ComplaintController@updateStatus');
    });

    // Phase 6: Dynamic Pricing Engine + Custom Package Builder
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('pricing-rules', '\App\Http\Controllers\PricingRuleController')->only(['index', 'store', 'update', 'destroy']);
        Route::post('/pricing-rules/preview', '\App\Http\Controllers\PricingRuleController@preview');
        Route::post('/package-builder/build', '\App\Http\Controllers\PackageBuilderController@build');
    });

    // Phase 6: Automation Engine + Templates + Dashboard
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Automation Management
        Route::apiResource('automations', '\App\Http\Controllers\AutomationController')->only(['index', 'store', 'show', 'update']);
        Route::post('/automations/{id}/execute', '\App\Http\Controllers\AutomationController@execute');
        Route::post('/automations/{id}/test', '\App\Http\Controllers\AutomationController@test');
        
        // Automation Templates
        Route::get('/automation-templates', '\App\Http\Controllers\AutomationTemplateController@index');
        Route::get('/automation-templates/{id}', '\App\Http\Controllers\AutomationTemplateController@show');
        Route::get('/automation-templates/category/{category}', '\App\Http\Controllers\AutomationTemplateController@getByCategory');
        Route::post('/automation-templates/{id}/use', '\App\Http\Controllers\AutomationTemplateController@useTemplate');
        
        // Automation Logs
        Route::get('/automations/{automationId}/logs', '\App\Http\Controllers\AutomationLogController@getAutomationLogs');
        Route::get('/automations/{automationId}/stats', '\App\Http\Controllers\AutomationLogController@getStats');
        Route::delete('/automations/{automationId}/logs', '\App\Http\Controllers\AutomationLogController@clearLogs');
        
        // Automation Dashboard
        Route::get('/automation-dashboard/summary', '\App\Http\Controllers\AutomationDashboardController@getSummary');
        Route::get('/automation-dashboard/metrics', '\App\Http\Controllers\AutomationDashboardController@getMetrics');
    });

    // Phase 7: AI & Analytics + Reports + Insights + Segmentation + Predictions
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Analytics
        Route::get('/analytics/metrics', '\App\Http\Controllers\AnalyticsController@getMetrics');
        Route::get('/analytics/metric/{type}', '\App\Http\Controllers\AnalyticsController@getMetricByType');
        
        // Reports
        Route::get('/reports', '\App\Http\Controllers\ReportController@index');
        Route::post('/reports', '\App\Http\Controllers\ReportController@create');
        Route::post('/reports/{id}/generate', '\App\Http\Controllers\ReportController@generate');
        Route::post('/reports/{id}/schedule', '\App\Http\Controllers\ReportController@schedule');
        
        // Insights
        Route::get('/insights', '\App\Http\Controllers\InsightController@list');
        Route::get('/insights/type/{type}', '\App\Http\Controllers\InsightController@getByType');
        Route::get('/insights/trending', '\App\Http\Controllers\InsightController@getTrending');
        
        // Customer Segments
        Route::get('/segments', '\App\Http\Controllers\SegmentController@list');
        Route::post('/segments', '\App\Http\Controllers\SegmentController@create');
        Route::get('/segments/{id}/members', '\App\Http\Controllers\SegmentController@getMembers');
        
        // Dashboard
        Route::get('/dashboard/default', '\App\Http\Controllers\DashboardController@getDefault');
        Route::put('/dashboard/{id}', '\App\Http\Controllers\DashboardController@update');
        Route::get('/dashboard/kpi', '\App\Http\Controllers\DashboardController@getKPI');
    });

    // Phase 8: Offline & PWA + Sync Engine + Cache Management
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Sync & Offline
        Route::post('/sync', '\App\Http\Controllers\SyncController@syncData');
        Route::get('/sync/pending', '\App\Http\Controllers\SyncController@getPendingSync');
        Route::get('/sync/status/{batchId}', '\App\Http\Controllers\SyncController@getSyncStatus');
        Route::post('/sync/retry-failed', '\App\Http\Controllers\SyncController@resyncFailed');
        
        // Cache Management
        Route::get('/cache/policies', '\App\Http\Controllers\CacheController@getCachePolicies');
        Route::post('/cache/policies', '\App\Http\Controllers\CacheController@createPolicy');
        Route::post('/cache/clear', '\App\Http\Controllers\CacheController@clearCache');
        Route::get('/cache/stats', '\App\Http\Controllers\CacheController@getCacheStats');
        
        // PWA Configuration
        Route::get('/pwa/manifest.json', '\App\Http\Controllers\PWAController@getManifest');
        Route::put('/pwa/settings', '\App\Http\Controllers\PWAController@updateSettings');
        Route::get('/sw.js', '\App\Http\Controllers\PWAController@getServiceWorker');
        
        // Offline Data
        Route::get('/offline/data', '\App\Http\Controllers\OfflineController@downloadOfflineData');
        Route::get('/offline/status', '\App\Http\Controllers\OfflineController@getOfflineStatus');
        Route::post('/offline/sync', '\App\Http\Controllers\OfflineController@syncOfflineChanges');
        Route::post('/offline/clear', '\App\Http\Controllers\OfflineController@clearOfflineData');
    });

    // Phase 9: Production Hardening & Deployment
    Route::middleware(['jwt.auth', 'security.headers', 'rate.limit'])->group(function () {
        Route::get('/health', '\App\Http\Controllers\HealthController@status');
        Route::get('/health/detailed', '\App\Http\Controllers\HealthController@detailed');
    });
    
    // Phase 2: CRM completion — Companies, Contacts, Notes, Documents, Tags,
    // Custom Fields, Reminders, Customer Timeline
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('companies', '\App\Http\Controllers\CompanyController')->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::apiResource('contacts', '\App\Http\Controllers\ContactController')->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::get('/notes', '\App\Http\Controllers\NoteController@index');
        Route::post('/notes', '\App\Http\Controllers\NoteController@store');
        Route::put('/notes/{id}', '\App\Http\Controllers\NoteController@update');
        Route::delete('/notes/{id}', '\App\Http\Controllers\NoteController@destroy');

        Route::get('/documents', '\App\Http\Controllers\DocumentController@index');
        Route::post('/documents', '\App\Http\Controllers\DocumentController@store');
        Route::delete('/documents/{id}', '\App\Http\Controllers\DocumentController@destroy');

        Route::apiResource('tags', '\App\Http\Controllers\TagController')->only(['index', 'store', 'destroy']);
        Route::post('/tags/attach', '\App\Http\Controllers\TagController@attach');
        Route::post('/tags/detach', '\App\Http\Controllers\TagController@detach');

        Route::get('/custom-fields', '\App\Http\Controllers\CustomFieldController@index');
        Route::post('/custom-fields', '\App\Http\Controllers\CustomFieldController@store');
        Route::delete('/custom-fields/{id}', '\App\Http\Controllers\CustomFieldController@destroy');
        Route::post('/custom-fields/value', '\App\Http\Controllers\CustomFieldController@setValue');
        Route::get('/custom-fields/values', '\App\Http\Controllers\CustomFieldController@valuesFor');

        Route::get('/reminders', '\App\Http\Controllers\ReminderController@index');
        Route::get('/reminders/due', '\App\Http\Controllers\ReminderController@due');
        Route::post('/reminders', '\App\Http\Controllers\ReminderController@store');
        Route::put('/reminders/{id}/complete', '\App\Http\Controllers\ReminderController@complete');
        Route::delete('/reminders/{id}', '\App\Http\Controllers\ReminderController@destroy');

        Route::get('/customers/{customerId}/timeline', '\App\Http\Controllers\CustomerTimelineController@show');
    });

    // Phase 0: Vendors (distinct from Suppliers — contracted package/service providers)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('vendors', '\App\Http\Controllers\VendorController')->only(['index', 'store', 'show', 'update']);
    });

    // Phase 0: Support Tickets
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('support-tickets', '\App\Http\Controllers\SupportTicketController')->only(['index', 'store', 'show']);
        Route::put('/support-tickets/{id}/status', '\App\Http\Controllers\SupportTicketController@updateStatus');
        Route::post('/support-tickets/{id}/escalate', '\App\Http\Controllers\SupportTicketController@escalate');
        Route::post('/support-tickets/{id}/reply', '\App\Http\Controllers\SupportTicketController@reply');
    });

    // Admin endpoints (require super-admin role).
    // Prefix is driven by config('admin.path') (env: ADMIN_URL_PATH) so the
    // admin URL segment can change without touching route definitions,
    // auth, or RBAC.
    Route::prefix(config('admin.path'))->middleware(['jwt.auth', 'security.headers'])->group(function () {
        Route::post('/optimize-db', '\App\Http\Controllers\AdminController@optimizeDatabase');
        Route::post('/analyze-db', '\App\Http\Controllers\AdminController@analyzeDatabase');
        Route::post('/backup', '\App\Http\Controllers\AdminController@createBackup');
        Route::get('/backups', '\App\Http\Controllers\AdminController@listBackups');
    });
});
