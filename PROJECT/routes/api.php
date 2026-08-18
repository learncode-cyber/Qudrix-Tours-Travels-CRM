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
});

    // Phase 1: Customer Management (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('customers', 'CustomerController');
        Route::post('/customers/{id}/family', 'CustomerController@addFamily');
        Route::get('/customers/{id}/family', 'CustomerController@getFamily');
    });

    // Phase 1: Lead Management (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('leads', 'LeadController')->only(['index', 'store', 'show']);
        Route::put('/leads/{id}/status', 'LeadController@updateStatus');
        Route::put('/leads/{id}/assign', 'LeadController@assignLead');
        Route::post('/leads/{id}/score', 'LeadController@scoreLeadForConversion');
        Route::post('/leads/{id}/follow-up', 'LeadController@scheduleFollowUp');
        Route::get('/leads/pending/follow-ups', 'LeadController@pendingFollowUps');
    });

    // Phase 1: Communication (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('communications', 'CommunicationController')->only(['index', 'store']);
        Route::get('/customers/{customerId}/communications', 'CommunicationController@getCustomerCommunications');
        Route::put('/communications/{id}/read', 'CommunicationController@markAsRead');
        Route::get('/communications/stats', 'CommunicationController@getCommunicationStats');
    });

    // Phase 1: Task Management (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        Route::apiResource('tasks', 'TaskController');
        Route::put('/tasks/{id}/complete', 'TaskController@markComplete');
        Route::put('/tasks/{id}/incomplete', 'TaskController@markIncomplete');
        Route::get('/tasks/stats', 'TaskController@getTaskStats');
    });

    // Phase 2: Sales Pipeline & Quotations (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Quotation Management
        Route::apiResource('quotations', 'QuotationController')->except('destroy');
        Route::post('/quotations/{id}/send', 'QuotationController@sendQuotation');
        Route::get('/quotations/stats', 'QuotationController@getQuotationStats');

        // Proposal Management
        Route::apiResource('proposals', 'ProposalController')->only(['index', 'show']);
        Route::post('/proposals/from-quotation', 'ProposalController@createFromQuotation');
        Route::post('/proposals/{id}/send', 'ProposalController@sendProposal');
        Route::post('/proposals/{id}/sign', 'ProposalController@signProposal');
        Route::post('/proposals/{id}/reject', 'ProposalController@rejectProposal');
        Route::get('/proposals/stats', 'ProposalController@getProposalStats');

        // Sales Pipeline
        Route::get('/pipeline/full', 'PipelineController@getFullPipeline');
        Route::get('/pipeline/lead/{leadId}', 'PipelineController@getLeadPipeline');
        Route::post('/pipeline/activity', 'PipelineController@recordActivity');
        Route::put('/pipeline/stage', 'PipelineController@updateLeadStage');
        Route::get('/pipeline/metrics', 'PipelineController@getPipelineMetrics');
    });

    // Phase 3: Booking Engine (protected routes)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Booking Management
        Route::apiResource('bookings', 'BookingController');
        Route::post('/bookings/{id}/confirm', 'BookingController@confirmBooking');
        Route::post('/bookings/{id}/cancel', 'BookingController@cancelBooking');
        Route::get('/bookings/stats', 'BookingController@getBookingStats');

        // Booking Travelers
        Route::post('/travelers/add', 'TravelerController@addTraveler');
        Route::get('/bookings/{bookingId}/travelers', 'TravelerController@getTravelers');
        Route::put('/travelers/{id}', 'TravelerController@updateTraveler');
        Route::delete('/travelers/{id}', 'TravelerController@removeTraveler');
        Route::get('/travelers/{id}/details', 'TravelerController@getTravelerDetails');

        // Booking Itinerary
        Route::post('/itinerary/create', 'ItineraryController@createItinerary');
        Route::get('/bookings/{bookingId}/itinerary', 'ItineraryController@getItinerary');
        Route::put('/itinerary/{id}', 'ItineraryController@updateItinerary');
        Route::delete('/itinerary/{id}', 'ItineraryController@deleteItinerary');
        Route::get('/bookings/{bookingId}/itinerary/pdf', 'ItineraryController@generateItineraryPdf');

        // Group Bookings
        Route::apiResource('groups', 'GroupBookingController')->only(['index', 'store', 'show']);
        Route::post('/groups/{groupId}/bookings', 'GroupBookingController@addBookingToGroup');
        Route::get('/groups/{groupId}/bookings', 'GroupBookingController@getGroupBookings');
        Route::get('/groups/{groupId}/stats', 'GroupBookingController@getGroupStats');
    });

    // Phase 4: Travel Management (Flights, Hotels, Transport, Destinations, Visa)
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Flights
        Route::apiResource('flights', 'FlightController');
        Route::post('/flights/book', 'FlightController@bookFlight');

        // Hotels
        Route::apiResource('hotels', 'HotelController');
        Route::post('/hotels/book', 'HotelController@bookHotel');

        // Transport
        Route::apiResource('transports', 'TransportController');
        Route::post('/transports/book', 'TransportController@bookTransport');

        // Destinations
        Route::apiResource('destinations', 'DestinationController');

        // Visas
        Route::apiResource('visas', 'VisaController');
        Route::post('/visas/{id}/submit', 'VisaController@submitApplication');
        Route::post('/visas/{id}/approve', 'VisaController@approveVisa');
        Route::get('/visas/booking/{bookingId}/status', 'VisaController@getVisaStatus');
    });

    // Phase 5: Hajj/Umrah/Tours & Expense/Supplier/Complaint Management
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Hajj Packages
        Route::apiResource('hajj', 'HajjController')->only(['index', 'store', 'show', 'update']);
        
        // Umrah Packages
        Route::apiResource('umrah', 'UmrahController')->only(['index', 'store', 'show']);
        
        // Tour Packages
        Route::apiResource('tours', 'TourController')->only(['index', 'store', 'show', 'update']);
        
        // Expenses
        Route::post('/expenses', 'ExpenseController@create');
        Route::get('/bookings/{bookingId}/expenses', 'ExpenseController@getByBooking');
        
        // Suppliers
        Route::apiResource('suppliers', 'SupplierController');
        
        // Complaints
        Route::get('/complaints', 'ComplaintController@index');
        Route::post('/complaints', 'ComplaintController@create');
        Route::put('/complaints/{id}/status', 'ComplaintController@updateStatus');
    });

    // Phase 6: Automation Engine + Templates + Dashboard
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Automation Management
        Route::apiResource('automations', 'AutomationController')->only(['index', 'store', 'show', 'update']);
        Route::post('/automations/{id}/execute', 'AutomationController@execute');
        Route::post('/automations/{id}/test', 'AutomationController@test');
        
        // Automation Templates
        Route::get('/automation-templates', 'AutomationTemplateController@index');
        Route::get('/automation-templates/{id}', 'AutomationTemplateController@show');
        Route::get('/automation-templates/category/{category}', 'AutomationTemplateController@getByCategory');
        Route::post('/automation-templates/{id}/use', 'AutomationTemplateController@useTemplate');
        
        // Automation Logs
        Route::get('/automations/{automationId}/logs', 'AutomationLogController@getAutomationLogs');
        Route::get('/automations/{automationId}/stats', 'AutomationLogController@getStats');
        Route::delete('/automations/{automationId}/logs', 'AutomationLogController@clearLogs');
        
        // Automation Dashboard
        Route::get('/automation-dashboard/summary', 'AutomationDashboardController@getSummary');
        Route::get('/automation-dashboard/metrics', 'AutomationDashboardController@getMetrics');
    });

    // Phase 7: AI & Analytics + Reports + Insights + Segmentation + Predictions
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Analytics
        Route::get('/analytics/metrics', 'AnalyticsController@getMetrics');
        Route::get('/analytics/metric/{type}', 'AnalyticsController@getMetricByType');
        
        // Reports
        Route::get('/reports', 'ReportController@index');
        Route::post('/reports', 'ReportController@create');
        Route::post('/reports/{id}/generate', 'ReportController@generate');
        Route::post('/reports/{id}/schedule', 'ReportController@schedule');
        
        // Insights
        Route::get('/insights', 'InsightController@list');
        Route::get('/insights/type/{type}', 'InsightController@getByType');
        Route::get('/insights/trending', 'InsightController@getTrending');
        
        // Customer Segments
        Route::get('/segments', 'SegmentController@list');
        Route::post('/segments', 'SegmentController@create');
        Route::get('/segments/{id}/members', 'SegmentController@getMembers');
        
        // Dashboard
        Route::get('/dashboard/default', 'DashboardController@getDefault');
        Route::put('/dashboard/{id}', 'DashboardController@update');
        Route::get('/dashboard/kpi', 'DashboardController@getKPI');
    });

    // Phase 8: Offline & PWA + Sync Engine + Cache Management
    Route::middleware(['jwt.auth', 'tenant', 'audit'])->group(function () {
        // Sync & Offline
        Route::post('/sync', 'SyncController@syncData');
        Route::get('/sync/pending', 'SyncController@getPendingSync');
        Route::get('/sync/status/{batchId}', 'SyncController@getSyncStatus');
        Route::post('/sync/retry-failed', 'SyncController@resyncFailed');
        
        // Cache Management
        Route::get('/cache/policies', 'CacheController@getCachePolicies');
        Route::post('/cache/policies', 'CacheController@createPolicy');
        Route::post('/cache/clear', 'CacheController@clearCache');
        Route::get('/cache/stats', 'CacheController@getCacheStats');
        
        // PWA Configuration
        Route::get('/pwa/manifest.json', 'PWAController@getManifest');
        Route::put('/pwa/settings', 'PWAController@updateSettings');
        Route::get('/sw.js', 'PWAController@getServiceWorker');
        
        // Offline Data
        Route::get('/offline/data', 'OfflineController@downloadOfflineData');
        Route::get('/offline/status', 'OfflineController@getOfflineStatus');
        Route::post('/offline/sync', 'OfflineController@syncOfflineChanges');
        Route::post('/offline/clear', 'OfflineController@clearOfflineData');
    });

    // Phase 9: Production Hardening & Deployment
    Route::middleware(['jwt.auth', 'security.headers', 'rate.limit'])->group(function () {
        Route::get('/health', 'HealthController@status');
        Route::get('/health/detailed', 'HealthController@detailed');
    });
    
    // Admin endpoints (require super-admin role)
    Route::middleware(['jwt.auth', 'security.headers'])->group(function () {
        Route::post('/admin/optimize-db', 'AdminController@optimizeDatabase');
        Route::post('/admin/analyze-db', 'AdminController@analyzeDatabase');
        Route::post('/admin/backup', 'AdminController@createBackup');
        Route::get('/admin/backups', 'AdminController@listBackups');
    });
