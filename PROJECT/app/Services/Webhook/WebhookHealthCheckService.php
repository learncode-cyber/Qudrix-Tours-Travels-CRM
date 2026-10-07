<?php

namespace App\Services\Webhook;

use App\Models\Webhook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WebhookHealthCheckService
{
    /**
     * Run complete system health check
     */
    public function runSystemHealthCheck(): array
    {
        return [
            'timestamp' => now(),
            'database' => $this->checkDatabase(),
            'webhooks' => $this->checkWebhooks(),
            'deliveries' => $this->checkDeliveries(),
            'performance' => $this->checkPerformance(),
            'alerts' => $this->checkAlerts(),
            'overall_status' => $this->determineOverallStatus(),
        ];
    }

    /**
     * Check database health
     */
    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            
            $tableCount = DB::select("
                SELECT COUNT(*) as count FROM information_schema.tables 
                WHERE table_schema = ?
            ", [config('database.connections.mysql.database')]);

            return [
                'status' => 'healthy',
                'connected' => true,
                'tables' => $tableCount[0]->count ?? 0,
                'response_time_ms' => $this->measureDatabaseLatency(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check webhook system health
     */
    protected function checkWebhooks(): array
    {
        $total = Webhook::count();
        $active = Webhook::where('is_active', true)->count();
        $inactive = Webhook::where('is_active', false)->count();

        $last24h = now()->subHours(24);
        $recentDeliveries = DB::table('webhook_deliveries')
            ->where('created_at', '>=', $last24h)
            ->count();

        return [
            'total_webhooks' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'status' => $active > 0 ? 'healthy' : 'unhealthy',
            'recent_deliveries_24h' => $recentDeliveries,
            'average_events_per_webhook' => $total > 0 ? round($recentDeliveries / $total, 2) : 0,
        ];
    }

    /**
     * Check delivery system health
     */
    protected function checkDeliveries(): array
    {
        $last24h = now()->subHours(24);
        $last7d = now()->subDays(7);

        $total24h = DB::table('webhook_deliveries')
            ->where('created_at', '>=', $last24h)
            ->count();

        $successful24h = DB::table('webhook_deliveries')
            ->where('created_at', '>=', $last24h)
            ->where('status', 'delivered')
            ->count();

        $failed24h = DB::table('webhook_deliveries')
            ->where('created_at', '>=', $last24h)
            ->where('status', 'failed')
            ->count();

        $pending = DB::table('webhook_deliveries')
            ->where('status', 'pending')
            ->count();

        $avgRetries = DB::table('webhook_deliveries')
            ->where('created_at', '>=', $last24h)
            ->avg('retry_count') ?? 0;

        return [
            'deliveries_24h' => $total24h,
            'successful_24h' => $successful24h,
            'failed_24h' => $failed24h,
            'pending' => $pending,
            'success_rate_24h' => $total24h > 0 ? round(($successful24h / $total24h) * 100, 2) : 0,
            'average_retries' => round($avgRetries, 2),
            'status' => ($total24h > 0 && ($successful24h / $total24h) > 0.8) ? 'healthy' : 'degraded',
        ];
    }

    /**
     * Check performance metrics
     */
    protected function checkPerformance(): array
    {
        $last24h = now()->subHours(24);

        $avgResponseTime = DB::table('webhook_deliveries')
            ->where('created_at', '>=', $last24h)
            ->where('status', 'delivered')
            ->selectRaw('
                AVG(TIMESTAMPDIFF(MILLISECOND, created_at, updated_at)) as avg_time,
                MIN(TIMESTAMPDIFF(MILLISECOND, created_at, updated_at)) as min_time,
                MAX(TIMESTAMPDIFF(MILLISECOND, created_at, updated_at)) as max_time
            ')
            ->first();

        $dbLatency = $this->measureDatabaseLatency();
        $cacheLatency = $this->measureCacheLatency();

        return [
            'database_latency_ms' => $dbLatency,
            'cache_latency_ms' => $cacheLatency,
            'average_delivery_time_ms' => round($avgResponseTime->avg_time ?? 0, 2),
            'min_delivery_time_ms' => round($avgResponseTime->min_time ?? 0, 2),
            'max_delivery_time_ms' => round($avgResponseTime->max_time ?? 0, 2),
            'status' => ($dbLatency < 100 && $cacheLatency < 50) ? 'healthy' : 'degraded',
        ];
    }

    /**
     * Check for system alerts
     */
    protected function checkAlerts(): array
    {
        $alerts = [];
        $last24h = now()->subHours(24);

        // High failure rate alert
        $total24h = DB::table('webhook_deliveries')
            ->where('created_at', '>=', $last24h)
            ->count();

        $failed24h = DB::table('webhook_deliveries')
            ->where('created_at', '>=', $last24h)
            ->where('status', 'failed')
            ->count();

        if ($total24h > 0) {
            $failureRate = ($failed24h / $total24h) * 100;
            if ($failureRate > 10) {
                $alerts[] = [
                    'severity' => 'critical',
                    'message' => "High failure rate: {$failureRate}%",
                ];
            }
        }

        // Pending deliveries check
        $pending = DB::table('webhook_deliveries')
            ->where('status', 'pending')
            ->count();

        if ($pending > 1000) {
            $alerts[] = [
                'severity' => 'warning',
                'message' => "High pending deliveries: {$pending}",
            ];
        }

        // Database connection check
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $alerts[] = [
                'severity' => 'critical',
                'message' => 'Database connection failed',
            ];
        }

        return $alerts;
    }

    /**
     * Determine overall system status
     */
    protected function determineOverallStatus(): string
    {
        $checks = [
            $this->checkDatabase()['status'],
            $this->checkWebhooks()['status'],
            $this->checkDeliveries()['status'],
            $this->checkPerformance()['status'],
        ];

        if (in_array('unhealthy', $checks)) {
            return 'unhealthy';
        } elseif (in_array('degraded', $checks)) {
            return 'degraded';
        }

        return 'healthy';
    }

    /**
     * Measure database latency
     */
    protected function measureDatabaseLatency(): float
    {
        $start = microtime(true);
        DB::select('SELECT 1');
        $end = microtime(true);
        
        return round(($end - $start) * 1000, 2);
    }

    /**
     * Measure cache latency
     */
    protected function measureCacheLatency(): float
    {
        $start = microtime(true);
        Cache::get('health_check_test');
        Cache::put('health_check_test', true, 60);
        $end = microtime(true);
        
        return round(($end - $start) * 1000, 2);
    }

    /**
     * Get cached health status (updated every minute)
     */
    public function getCachedHealthStatus(): array
    {
        return Cache::remember('system_health_status', 60, function () {
            return $this->runSystemHealthCheck();
        });
    }
}
