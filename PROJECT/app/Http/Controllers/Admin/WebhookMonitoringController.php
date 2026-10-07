<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\Webhook\WebhookMonitoringService;
use App\Services\Webhook\WebhookHealthCheckService;
use App\Services\Webhook\WebhookAuditLoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * FIX (2026-09-25, follow-up to CRITICAL_SECURITY_WEBHOOK_IDOR_REPORT.md's
 * "aggregate endpoints" note): monitorAllWebhooks/getSecurityAuditLog/
 * generateComplianceReport/exportAuditLog previously queried across every
 * tenant with zero filtering — see the service files for the full history.
 * Every call into those services below now passes $this->currentTenantId($request).
 * getSystemHealth/getCachedHealth are left as pure aggregate counts (no
 * URLs, names, or other tenant-identifying detail — reviewed in
 * WebhookHealthCheckService) and are intentionally left tenant-unaware,
 * same as the platform-wide AdminApiKeyController precedent.
 */
class WebhookMonitoringController extends Controller
{
    public function __construct(
        private WebhookMonitoringService $monitoringService,
        private WebhookHealthCheckService $healthCheckService,
        private WebhookAuditLoggingService $auditService,
    ) {}

    /**
     * This route group uses the plain 'auth' middleware (default guard),
     * not this codebase's usual 'jwt.auth' — which means $request->user
     * (the custom property JwtMiddleware sets elsewhere) may not be
     * populated here. Falls back to auth()->user(), same as
     * TenantMiddleware and EnsureWebhookBelongsToTenant already do, rather
     * than assuming $request->user directly and risking a null-property
     * error instead of a correct tenant id.
     */
    protected function currentTenantId(Request $request): int
    {
        $user = $request->user ?? auth()->user();

        if (!$user?->tenant_id) {
            abort(401, 'Unauthenticated.');
        }

        return $user->tenant_id;
    }

    public function getWebhookHealth(Webhook $webhook): JsonResponse
    {
        // Tenant ownership of $webhook is enforced by the 'webhook.tenant'
        // middleware on this route group.
        $health = $this->monitoringService->getWebhookHealth($webhook);

        return response()->json($health);
    }

    public function monitorAllWebhooks(Request $request): JsonResponse
    {
        $monitoring = $this->monitoringService->monitorAllWebhooks($this->currentTenantId($request));

        return response()->json($monitoring);
    }

    public function getSystemHealth(): JsonResponse
    {
        $health = $this->healthCheckService->runSystemHealthCheck();

        return response()->json($health);
    }

    public function getCachedHealth(): JsonResponse
    {
        $health = $this->healthCheckService->getCachedHealthStatus();

        return response()->json($health);
    }

    public function getAuditTrail(Webhook $webhook, Request $request): JsonResponse
    {
        $limit = $request->query('limit', 100);
        $trail = $this->auditService->getWebhookAuditTrail($webhook, $limit);

        return response()->json($trail);
    }

    public function getDeliveryAuditTrail(Webhook $webhook, Request $request): JsonResponse
    {
        $limit = $request->query('limit', 50);
        $trail = $this->auditService->getDeliveryAuditTrail($webhook, $limit);

        return response()->json($trail);
    }

    public function getSecurityAuditLog(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 100);
        $log = $this->auditService->getSecurityAuditLog($this->currentTenantId($request), $limit);

        return response()->json($log);
    }

    public function generateComplianceReport(Request $request): JsonResponse
    {
        $days = $request->query('days', 30);
        $report = $this->auditService->generateComplianceReport($this->currentTenantId($request), $days);

        return response()->json($report);
    }

    public function exportAuditLog(Request $request): JsonResponse
    {
        $type = $request->query('type', 'webhooks'); // webhooks, deliveries, security
        $format = $request->query('format', 'json'); // json, csv

        $data = $this->auditService->exportAuditLog($this->currentTenantId($request), $type, $format);

        return response()->json([
            'type' => $type,
            'format' => $format,
            'data' => $data,
            'exported_at' => now(),
        ]);
    }

    public function getAlerts(Request $request): JsonResponse
    {
        $monitoring = $this->monitoringService->monitorAllWebhooks($this->currentTenantId($request));
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

    public function getDashboardSummary(Request $request): JsonResponse
    {
        $monitoring = $this->monitoringService->monitorAllWebhooks($this->currentTenantId($request));
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
