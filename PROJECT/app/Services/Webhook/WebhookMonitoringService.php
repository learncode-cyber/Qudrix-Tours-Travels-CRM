<?php

namespace App\Services\Webhook;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WebhookMonitoringService
{
    /**
     * Get real-time webhook health status
     */
    public function getWebhookHealth(Webhook $webhook): array
    {
        $now = now();
        $last24h = $now->copy()->subHours(24);
        $last7d = $now->copy()->subDays(7);

        $deliveries24h = $webhook->deliveries()
            ->where('created_at', '>=', $last24h)
            ->get();

        $deliveries7d = $webhook->deliveries()
            ->where('created_at', '>=', $last7d)
            ->get();

        $lastDelivery = $webhook->deliveries()
            ->orderBy('created_at', 'desc')
            ->first();

        $successRate24h = $deliveries24h->count() > 0
            ? round(($deliveries24h->where('status', 'delivered')->count() / $deliveries24h->count()) * 100, 2)
            : 0;

        $failureRate24h = $deliveries24h->count() > 0
            ? round(($deliveries24h->where('status', 'failed')->count() / $deliveries24h->count()) * 100, 2)
            : 0;

        $avgResponseTime24h = $this->calculateAverageResponseTime($deliveries24h);

        return [
            'webhook_id' => $webhook->id,
            'webhook_name' => $webhook->name,
            'status' => $this->determineHealthStatus($successRate24h, $failureRate24h),
            'health_score' => $this->calculateHealthScore($webhook, $deliveries24h, $deliveries7d),
            'last_24_hours' => [
                'total_deliveries' => $deliveries24h->count(),
                'successful' => $deliveries24h->where('status', 'delivered')->count(),
                'failed' => $deliveries24h->where('status', 'failed')->count(),
                'pending' => $deliveries24h->where('status', 'pending')->count(),
                'success_rate' => $successRate24h,
                'failure_rate' => $failureRate24h,
                'average_response_time' => $avgResponseTime24h,
            ],
            'last_7_days' => [
                'total_deliveries' => $deliveries7d->count(),
                'successful' => $deliveries7d->where('status', 'delivered')->count(),
                'failed' => $deliveries7d->where('status', 'failed')->count(),
            ],
            'last_delivery' => [
                'timestamp' => $lastDelivery?->created_at?->toIso8601String(),
                'status' => $lastDelivery?->status,
                'response_time_ms' => $lastDelivery ? $this->calculateResponseTime($lastDelivery) : null,
            ],
            'alerts' => $this->generateAlerts($webhook, $successRate24h, $failureRate24h),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Monitor all webhooks
     */
    public function monitorAllWebhooks(): array
    {
        $webhooks = Webhook::where('is_active', true)->get();
        
        $results = [
            'total_webhooks' => $webhooks->count(),
            'healthy' => 0,
            'degraded' => 0,
            'unhealthy' => 0,
            'webhooks' => [],
            'summary' => $this->getSystemSummary($webhooks),
            'checked_at' => now()->toIso8601String(),
        ];

        foreach ($webhooks as $webhook) {
            $health = $this->getWebhookHealth($webhook);
            $results['webhooks'][] = $health;

            match ($health['status']) {
                'healthy' => $results['healthy']++,
                'degraded' => $results['degraded']++,
                'unhealthy' => $results['unhealthy']++,
            };
        }

        return $results;
    }

    /**
     * Get system-wide summary
     */
    protected function getSystemSummary($webhooks): array
    {
        $last24h = now()->subHours(24);
        
        $totalDeliveries = WebhookDelivery::where('created_at', '>=', $last24h)->count();
        $successfulDeliveries = WebhookDelivery::where('created_at', '>=', $last24h)
            ->where('status', 'delivered')
            ->count();
        $failedDeliveries = WebhookDelivery::where('created_at', '>=', $last24h)
            ->where('status', 'failed')
            ->count();

        return [
            'total_deliveries_24h' => $totalDeliveries,
            'successful_24h' => $successfulDeliveries,
            'failed_24h' => $failedDeliveries,
            'system_success_rate' => $totalDeliveries > 0
                ? round(($successfulDeliveries / $totalDeliveries) * 100, 2)
                : 0,
            'active_webhooks' => $webhooks->where('is_active', true)->count(),
            'inactive_webhooks' => $webhooks->where('is_active', false)->count(),
        ];
    }

    /**
     * Generate alerts for webhook
     */
    protected function generateAlerts(Webhook $webhook, float $successRate, float $failureRate): array
    {
        $alerts = [];

        // High failure rate alert
        if ($failureRate > 10) {
            $alerts[] = [
                'level' => 'critical',
                'message' => "Failure rate is {$failureRate}% (threshold: 10%)",
                'code' => 'high_failure_rate',
            ];
        } elseif ($failureRate > 5) {
            $alerts[] = [
                'level' => 'warning',
                'message' => "Failure rate is {$failureRate}% (threshold: 5%)",
                'code' => 'elevated_failure_rate',
            ];
        }

        // Low success rate alert
        if ($successRate < 80) {
            $alerts[] = [
                'level' => 'warning',
                'message' => "Success rate is {$successRate}% (threshold: 80%)",
                'code' => 'low_success_rate',
            ];
        }

        // Webhook inactive check
        if (!$webhook->is_active) {
            $alerts[] = [
                'level' => 'info',
                'message' => 'Webhook is inactive',
                'code' => 'webhook_inactive',
            ];
        }

        // Recent deliveries check
        $recentDeliveries = $webhook->deliveries()
            ->where('created_at', '>=', now()->subHours(1))
            ->count();

        if ($recentDeliveries === 0) {
            $alerts[] = [
                'level' => 'warning',
                'message' => 'No deliveries in the last hour',
                'code' => 'no_recent_deliveries',
            ];
        }

        return $alerts;
    }

    /**
     * Determine health status based on metrics
     */
    protected function determineHealthStatus(float $successRate, float $failureRate): string
    {
        if ($successRate >= 95 && $failureRate <= 2) {
            return 'healthy';
        } elseif ($successRate >= 80 && $failureRate <= 10) {
            return 'degraded';
        }

        return 'unhealthy';
    }

    /**
     * Calculate health score (0-100)
     */
    protected function calculateHealthScore(Webhook $webhook, $deliveries24h, $deliveries7d): int
    {
        $score = 100;

        // Success rate component (50 points)
        $successRate24h = $deliveries24h->count() > 0
            ? ($deliveries24h->where('status', 'delivered')->count() / $deliveries24h->count()) * 100
            : 0;
        $score -= (100 - $successRate24h) * 0.5;

        // Consistency component (30 points)
        $successRate7d = $deliveries7d->count() > 0
            ? ($deliveries7d->where('status', 'delivered')->count() / $deliveries7d->count()) * 100
            : 0;
        $consistency = abs($successRate24h - $successRate7d);
        $score -= min($consistency, 30);

        // Webhook status component (20 points)
        if (!$webhook->is_active) {
            $score -= 20;
        }

        return max(0, (int)$score);
    }

    /**
     * Calculate average response time
     */
    protected function calculateAverageResponseTime($deliveries): float
    {
        if ($deliveries->isEmpty()) {
            return 0;
        }

        $responseTimes = $deliveries
            ->map(fn($d) => $this->calculateResponseTime($d))
            ->filter()
            ->values();

        return $responseTimes->isEmpty() ? 0 : round($responseTimes->avg(), 2);
    }

    /**
     * Calculate response time from delivery
     */
    protected function calculateResponseTime($delivery): ?float
    {
        if (!$delivery->created_at || !$delivery->updated_at) {
            return null;
        }

        return $delivery->updated_at->diffInMilliseconds($delivery->created_at);
    }
}
