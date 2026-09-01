<?php

namespace Tests\Api;

use Tests\TestCase;
use App\Models\Webhook;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\Webhook\WebhookMonitoringService;
use App\Services\Webhook\WebhookHealthCheckService;
use App\Services\Webhook\WebhookAuditLoggingService;

class WebhookMonitoringTest extends TestCase
{
    private WebhookMonitoringService $monitoringService;
    private WebhookHealthCheckService $healthCheckService;
    private WebhookAuditLoggingService $auditService;
    private User $user;
    private Webhook $webhook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->monitoringService = app(WebhookMonitoringService::class);
        $this->healthCheckService = app(WebhookHealthCheckService::class);
        $this->auditService = app(WebhookAuditLoggingService::class);

        $this->user = User::factory()->create(['role' => 'super-admin']);
        $this->webhook = Webhook::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test webhook health check
     */
    public function test_webhook_health_check(): void
    {
        // Create some deliveries
        for ($i = 0; $i < 8; $i++) {
            $this->webhook->deliveries()->create([
                'event_type' => 'booking.created',
                'payload' => json_encode(['id' => $i]),
                'status' => 'delivered',
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            $this->webhook->deliveries()->create([
                'event_type' => 'booking.created',
                'payload' => json_encode(['id' => $i + 8]),
                'status' => 'failed',
            ]);
        }

        $health = $this->monitoringService->getWebhookHealth($this->webhook);

        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('health_score', $health);
        $this->assertArrayHasKey('last_24_hours', $health);
        $this->assertEquals(10, $health['last_24_hours']['total_deliveries']);
        $this->assertEquals(80, $health['last_24_hours']['success_rate']);
    }

    /**
     * Test webhook health status
     */
    public function test_webhook_health_status(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->webhook->deliveries()->create([
                'event_type' => 'booking.created',
                'payload' => json_encode(['id' => $i]),
                'status' => 'delivered',
            ]);
        }

        $health = $this->monitoringService->getWebhookHealth($this->webhook);

        $this->assertEquals('healthy', $health['status']);
        $this->assertGreaterThanOrEqual(95, $health['health_score']);
    }

    /**
     * Test all webhooks monitoring
     */
    public function test_monitor_all_webhooks(): void
    {
        // Create multiple webhooks
        $webhook2 = Webhook::factory()->create(['user_id' => $this->user->id]);
        
        $monitoring = $this->monitoringService->monitorAllWebhooks();

        $this->assertArrayHasKey('total_webhooks', $monitoring);
        $this->assertArrayHasKey('healthy', $monitoring);
        $this->assertArrayHasKey('webhooks', $monitoring);
        $this->assertIsArray($monitoring['webhooks']);
    }

    /**
     * Test system health check
     */
    public function test_system_health_check(): void
    {
        $health = $this->healthCheckService->runSystemHealthCheck();

        $this->assertArrayHasKey('database', $health);
        $this->assertArrayHasKey('webhooks', $health);
        $this->assertArrayHasKey('deliveries', $health);
        $this->assertArrayHasKey('performance', $health);
        $this->assertArrayHasKey('overall_status', $health);

        $this->assertIn($health['overall_status'], ['healthy', 'degraded', 'unhealthy']);
    }

    /**
     * Test database health check
     */
    public function test_database_health_check(): void
    {
        $health = $this->healthCheckService->runSystemHealthCheck();

        $this->assertEquals('healthy', $health['database']['status']);
        $this->assertTrue($health['database']['connected']);
        $this->assertGreaterThan(0, $health['database']['tables']);
    }

    /**
     * Test audit logging
     */
    public function test_audit_logging(): void
    {
        $this->actingAs($this->user);

        $this->auditService->logWebhookAction('create', $this->webhook, [
            'name' => 'Test Webhook',
        ]);

        $trail = $this->auditService->getWebhookAuditTrail($this->webhook);

        $this->assertGreaterThan(0, $trail['total_logs']);
        $this->assertCount(1, $trail['logs']);
    }

    /**
     * Test delivery audit logging
     */
    public function test_delivery_audit_logging(): void
    {
        $this->webhook->deliveries()->create([
            'event_type' => 'booking.created',
            'payload' => json_encode(['id' => 1]),
            'status' => 'delivered',
        ]);

        $this->auditService->logDeliveryAttempt($this->webhook, 'booking.created', 
            ['id' => 1], 
            ['status' => 200, 'success' => true, 'time_ms' => 125]
        );

        $trail = $this->auditService->getDeliveryAuditTrail($this->webhook);

        $this->assertGreaterThan(0, $trail['total_deliveries_logged']);
    }

    /**
     * Test security audit logging
     */
    public function test_security_audit_logging(): void
    {
        $this->actingAs($this->user);

        $this->auditService->logSecurityEvent(
            'unauthorized_access_attempt',
            'critical',
            ['webhook_id' => $this->webhook->id]
        );

        $log = $this->auditService->getSecurityAuditLog();

        $this->assertGreaterThan(0, $log['total_events']);
    }

    /**
     * Test compliance report
     */
    public function test_compliance_report(): void
    {
        // Create deliveries
        for ($i = 0; $i < 10; $i++) {
            $this->webhook->deliveries()->create([
                'event_type' => 'booking.created',
                'payload' => json_encode(['id' => $i]),
                'status' => 'delivered',
            ]);
        }

        $report = $this->auditService->generateComplianceReport(30);

        $this->assertArrayHasKey('period', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('security_summary', $report);
        $this->assertGreaterThan(0, $report['summary']['total_deliveries']);
    }

    /**
     * Test webhook alerts
     */
    public function test_webhook_alerts(): void
    {
        // Create failures to trigger alerts
        for ($i = 0; $i < 20; $i++) {
            $this->webhook->deliveries()->create([
                'event_type' => 'booking.created',
                'payload' => json_encode(['id' => $i]),
                'status' => 'failed',
            ]);
        }

        $health = $this->monitoringService->getWebhookHealth($this->webhook);

        $this->assertNotEmpty($health['alerts']);
        $this->assertTrue(
            collect($health['alerts'])->contains(
                fn($alert) => $alert['code'] === 'high_failure_rate'
            )
        );
    }

    /**
     * Test cached health status
     */
    public function test_cached_health_status(): void
    {
        $health1 = $this->healthCheckService->getCachedHealthStatus();
        $health2 = $this->healthCheckService->getCachedHealthStatus();

        $this->assertEquals($health1, $health2);
    }

    /**
     * Test performance metrics
     */
    public function test_performance_metrics(): void
    {
        // Create a delivery with timing
        $this->webhook->deliveries()->create([
            'event_type' => 'booking.created',
            'payload' => json_encode(['id' => 1]),
            'status' => 'delivered',
        ]);

        $health = $this->healthCheckService->runSystemHealthCheck();

        $this->assertArrayHasKey('performance', $health);
        $this->assertArrayHasKey('database_latency_ms', $health['performance']);
        $this->assertArrayHasKey('cache_latency_ms', $health['performance']);
        $this->assertGreaterThanOrEqual(0, $health['performance']['database_latency_ms']);
    }

    /**
     * Test export audit log
     */
    public function test_export_audit_log(): void
    {
        $this->webhook->deliveries()->create([
            'event_type' => 'booking.created',
            'payload' => json_encode(['id' => 1]),
            'status' => 'delivered',
        ]);

        $json = $this->auditService->exportAuditLog('deliveries', 'json');
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
    }
}
