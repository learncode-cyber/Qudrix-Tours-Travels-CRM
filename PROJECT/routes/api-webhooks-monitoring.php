<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\WebhookMonitoringController;

/**
 * PHASE 4: Monitoring, Health Checks & Audit Logging
 * All routes require admin authentication
 * 
 * Base URI: /admin/api/webhooks-monitoring
 */

Route::prefix('admin/api/webhooks-monitoring')->middleware(['jwt.auth', 'tenant', 'audit'])->controller(WebhookMonitoringController::class)->group(function () {

    /**
     * Health Check Endpoints
     */
    Route::prefix('health')->group(function () {
        Route::get('system', 'getSystemHealth')
            ->name('webhook.monitoring.health.system');
        
        Route::get('system/cached', 'getCachedHealth')
            ->name('webhook.monitoring.health.cached');
        
        Route::get('webhooks/{webhook}', 'getWebhookHealth')
            ->name('webhook.monitoring.health.webhook');
    });

    /**
     * Monitoring Endpoints
     */
    Route::prefix('dashboard')->group(function () {
        Route::get('summary', 'getDashboardSummary')
            ->name('webhook.monitoring.dashboard.summary');
        
        Route::get('webhooks', 'monitorAllWebhooks')
            ->name('webhook.monitoring.dashboard.all');
        
        Route::get('alerts', 'getAlerts')
            ->name('webhook.monitoring.dashboard.alerts');
    });

    /**
     * Audit Log Endpoints
     */
    Route::prefix('audit')->group(function () {
        Route::get('webhooks/{webhook}', 'getAuditTrail')
            ->name('webhook.monitoring.audit.webhooks');
        
        Route::get('deliveries/{webhook}', 'getDeliveryAuditTrail')
            ->name('webhook.monitoring.audit.deliveries');
        
        Route::get('security', 'getSecurityAuditLog')
            ->name('webhook.monitoring.audit.security');
        
        Route::get('compliance', 'generateComplianceReport')
            ->name('webhook.monitoring.audit.compliance');
        
        Route::get('export', 'exportAuditLog')
            ->name('webhook.monitoring.audit.export');
    });

});
