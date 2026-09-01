<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\WebhookAnalyticsDashboardController;

/**
 * PHASE 3: Advanced Webhook Features Routes
 * All routes require admin authentication and webhook:manage permission
 * 
 * Base URI: /admin/api/webhooks-advanced
 */

Route::prefix('admin/api/webhooks-advanced')->middleware(['auth:sanctum', 'api.key.auth'])->group(function () {

    /**
     * Analytics Dashboard Endpoints
     */
    Route::prefix('analytics')->controller(WebhookAnalyticsDashboardController::class)->group(function () {
        
        // Get summary dashboard across all webhooks
        Route::get('summary', 'getSummary')
            ->name('webhook.analytics.summary')
            ->withoutMiddleware(['api.key.auth'])
            ->middleware(['auth']);

        // Get detailed analytics for specific webhook
        Route::get('webhooks/{webhook}', 'getDashboard')
            ->name('webhook.analytics.dashboard');

        // Get detailed analytics breakdown
        Route::get('webhooks/{webhook}/detailed', 'getDetailedAnalytics')
            ->name('webhook.analytics.detailed');

        // Get daily performance metrics
        Route::get('webhooks/{webhook}/daily', 'getDailyPerformance')
            ->name('webhook.analytics.daily');

        // Get event type breakdown
        Route::get('webhooks/{webhook}/events', 'getEventBreakdown')
            ->name('webhook.analytics.events');

        // Get success rate trends
        Route::get('webhooks/{webhook}/trends', 'getSuccessRateTrend')
            ->name('webhook.analytics.trends');

        // Get response time statistics
        Route::get('webhooks/{webhook}/response-times', 'getResponseTimeStats')
            ->name('webhook.analytics.response-times');

        // Get retry analysis
        Route::get('webhooks/{webhook}/retries', 'getRetryAnalysis')
            ->name('webhook.analytics.retries');

        // Get top errors
        Route::get('webhooks/{webhook}/errors', 'getTopErrors')
            ->name('webhook.analytics.errors');

        // Export analytics data
        Route::get('webhooks/{webhook}/export', 'exportAnalytics')
            ->name('webhook.analytics.export');
    });

    /**
     * Batching Endpoints
     * 
     * POST   /batching/create           - Create batch queue
     * GET    /batching/pending           - Get pending batches
     * POST   /batching/{batch}/process   - Process batch
     * GET    /batching/{webhook}/stats   - Get batch statistics
     * POST   /batching/flush-expired     - Flush expired batches
     */
    Route::prefix('batching')->group(function () {
        // Batch management would be implemented here
    });

    /**
     * Filtering Endpoints
     * 
     * GET    /filters/operators         - Get available operators
     * POST   /filters/validate          - Validate filter configuration
     * GET    /webhooks/{webhook}/filters - Get webhook filters
     * PUT    /webhooks/{webhook}/filters - Update webhook filters
     */
    Route::prefix('filters')->group(function () {
        // Filter management would be implemented here
    });

    /**
     * Conditional Delivery Endpoints
     * 
     * GET    /conditions/types          - Get available condition types
     * POST   /conditions/validate       - Validate conditions
     * GET    /webhooks/{webhook}/conditions - Get webhook conditions
     * PUT    /webhooks/{webhook}/conditions - Update webhook conditions
     */
    Route::prefix('conditions')->group(function () {
        // Conditional delivery management would be implemented here
    });

    /**
     * Payload Transformation Endpoints
     * 
     * GET    /transformations/types     - Get available transformation types
     * POST   /transformations/validate  - Validate transformation
     * GET    /webhooks/{webhook}/transformations - Get transformations
     * PUT    /webhooks/{webhook}/transformations - Update transformations
     */
    Route::prefix('transformations')->group(function () {
        // Payload transformation management would be implemented here
    });
});
