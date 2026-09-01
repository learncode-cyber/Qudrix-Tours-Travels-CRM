<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\Webhook\WebhookMonitoringService;
use App\Services\Webhook\WebhookHealthCheckService;
use App\Services\Webhook\WebhookAuditLoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebhookMonitoringController extends Controller
{
    /**
     * Constructor
     */
    public function __construct(
        private WebhookMonitoringService $monitoringService,
        private WebhookHealthCheckService $healthCheckService,
        private WebhookAuditLoggingService $auditService,
    ) {}

    /**
     * Get webhook health status
     */
    public function getWebhookHealth(Webhook $webhook): JsonResponse
    {
        $health = $this->monitoringService->getWebhookHealth($webhook);

        return response()->json($health);
    }

    /**
     * Monitor all webhooks
     */
    public function monitorAllWebhooks(): JsonResponse
    {
        $monitoring = $this->monitoringService->monitorAllWebhooks();

        return response()->json($monitoring);
    }

    /**
     * Get system health status
     */
    public function getSystemHealth(): JsonResponse
    {
        $health = $this->healthCheckService->runSystemHealthCheck();

        return response()->json($health);
    }

    /**
     * Get cached health status
     */
    public function getCachedHealth(): JsonResponse
    {
        $health = $this->healthCheckService->getCachedHealthStatus();

        return response()->json($health);
    }

    /**
     * Get webhook audit trail
     */
    public function getAuditTrail(Webhook $webhook, Request $request): JsonResponse
    {
        $limit = $request->query('limit', 100);
        $trail = $this->auditService->getWebhookAuditTrail($webhook, $limit);

        return response()->json($trail);
    }

    /**
     * Get delivery audit trail
     */
    public function getDeliveryAuditTrail(Webhook $webhook, Request $request): JsonResponse
    {
        $limit = $request->query('limit', 50);
        $trail = $this->auditService->getDeliveryAuditTrail($webhook, $limit);

        return response()->json($trail);
    }

    /**
     * Get security audit log
     */
    public function getSecurityAuditLog(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 100);
        $log = $this->auditService->getSecurityAuditLog($limit);

        return response()->json($log);
    }

    /**
     * Generate compliance report
     */
    public function generateComplianceReport(Request $request): JsonResponse
    {
        $days = $request->query('days', 30);
        $report = $this->auditService->generateComplianceReport($days);

        return response()->json($report);
    }

    /**
     * Export audit log
     */
    public function exportAuditLog(Request $request): JsonResponse
    {
        $type = $request->query('type', 'webhooks'); // webhooks, deliveries, security
        $format = $request->query('format', 'json'); // json, csv

        $data = $this->auditService->exportAuditLog($type, $format);

        return response()->json([
            'type' => $type,
            'format' => $format,
            'data' => $data,
            'exported_at' => now(),
        ]);
    }

    /**
     * Get real-time alerts
     */
    public function getAlerts(): JsonResponse
    {
        $monitoring = $this->monitoringService->monitorAllWebhooks();
        $alerts = [];

        foreach ($monitoring['webhooks'] as $webhook) {
            $alerts = array_merge($alerts, $webhook['alerts']);
        }

        return response()->json([
            'total_alerts' => count($alerts),
            'critical' => count(array_filter($alerts, fn($a) => $a['level'] === 'critical')),
            'warning' => count(array_filter($alerts, fn($a) => $a['level'] === 'warning')),
            'info' => count(array_filter($alerts, fn($a) => $a['level'] === 'info')),
            'alerts' => $alerts,
        ]);
    }

    /**
     * Get dashboard summary
     */
    public function getDashboardSummary(): JsonResponse
    {
        $monitoring = $this->monitoringService->monitorAllWebhooks();
        $health = $this->healthCheckService->runSystemHealthCheck();

        return response()->json([
            'system_status' => $health['overall_status'],
            'healthy_webhooks' => $monitoring['healthy'],
            'degraded_webhooks' => $monitoring['degraded'],
            'unhealthy_webhooks' => $monitoring['unhealthy'],
            'summary' => $monitoring['summary'],
            'performance' => $health['performance'],
            'alerts' => count($health['alerts']),
            'checked_at' => now(),
        ]);
    }
}
