<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\Webhook\WebhookAnalyticsService;
use App\Services\Webhook\WebhookBatchingService;
use App\Services\Webhook\WebhookConditionalDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebhookAnalyticsDashboardController extends Controller
{
    /**
     * Constructor
     */
    public function __construct(
        private WebhookAnalyticsService $analyticsService,
        private WebhookBatchingService $batchingService,
        private WebhookConditionalDeliveryService $conditionalService,
    ) {}

    /**
     * Get webhook analytics dashboard
     */
    public function getDashboard(Request $request, Webhook $webhook): JsonResponse
    {
        $period = $request->query('period', '7d');
        $this->validatePeriod($period);

        $analytics = $this->analyticsService->getAnalytics($webhook, $period);
        $batchStats = $this->batchingService->getBatchStatistics($webhook);
        $deliveryStats = $this->conditionalService->getDeliveryStats($webhook);

        return response()->json([
            'webhook' => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'url' => $webhook->url,
                'events' => $webhook->events,
                'active' => $webhook->is_active,
            ],
            'analytics' => $analytics,
            'batch_statistics' => $batchStats,
            'delivery_statistics' => $deliveryStats,
            'timestamp' => now(),
        ]);
    }

    /**
     * Get summary dashboard
     */
    public function getSummary(Request $request): JsonResponse
    {
        $period = $request->query('period', '7d');
        $this->validatePeriod($period);

        $webhooks = auth()->user()->webhooks ?? [];
        
        $summary = [
            'total_webhooks' => count($webhooks),
            'active_webhooks' => 0,
            'total_deliveries' => 0,
            'successful_deliveries' => 0,
            'failed_deliveries' => 0,
            'average_success_rate' => 0,
            'webhooks' => [],
        ];

        foreach ($webhooks as $webhook) {
            if ($webhook->is_active) {
                $summary['active_webhooks']++;
            }

            $analytics = $this->analyticsService->getAnalytics($webhook, $period);
            $stats = $analytics['summary'];

            $summary['total_deliveries'] += $stats['total_deliveries'];
            $summary['successful_deliveries'] += $stats['delivered'];
            $summary['failed_deliveries'] += $stats['failed'];

            $summary['webhooks'][] = [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'success_rate' => $stats['success_rate'],
                'deliveries' => $stats['total_deliveries'],
            ];
        }

        if ($summary['total_deliveries'] > 0) {
            $summary['average_success_rate'] = round(
                ($summary['successful_deliveries'] / $summary['total_deliveries']) * 100,
                2
            );
        }

        return response()->json($summary);
    }

    /**
     * Get detailed analytics
     */
    public function getDetailedAnalytics(Request $request, Webhook $webhook): JsonResponse
    {
        $period = $request->query('period', '7d');
        $this->validatePeriod($period);

        $analytics = $this->analyticsService->getAnalytics($webhook, $period);

        return response()->json($analytics);
    }

    /**
     * Get daily performance
     */
    public function getDailyPerformance(Request $request, Webhook $webhook): JsonResponse
    {
        $period = $request->query('period', '7d');
        $this->validatePeriod($period);

        $analytics = $this->analyticsService->getAnalytics($webhook, $period);

        return response()->json([
            'webhook_id' => $webhook->id,
            'period' => $period,
            'daily_stats' => $analytics['daily_stats'],
        ]);
    }

    /**
     * Get event breakdown
     */
    public function getEventBreakdown(Request $request, Webhook $webhook): JsonResponse
    {
        $period = $request->query('period', '7d');
        $this->validatePeriod($period);

        $analytics = $this->analyticsService->getAnalytics($webhook, $period);

        return response()->json([
            'webhook_id' => $webhook->id,
            'period' => $period,
            'events' => $analytics['event_breakdown'],
        ]);
    }

    /**
     * Get success rate trends
     */
    public function getSuccessRateTrend(Request $request, Webhook $webhook): JsonResponse
    {
        $period = $request->query('period', '7d');
        $this->validatePeriod($period);

        $analytics = $this->analyticsService->getAnalytics($webhook, $period);

        return response()->json([
            'webhook_id' => $webhook->id,
            'period' => $period,
            'trend' => $analytics['success_rate_trend'],
        ]);
    }

    /**
     * Get response time statistics
     */
    public function getResponseTimeStats(Request $request, Webhook $webhook): JsonResponse
    {
        $period = $request->query('period', '7d');
        $this->validatePeriod($period);

        $analytics = $this->analyticsService->getAnalytics($webhook, $period);

        return response()->json([
            'webhook_id' => $webhook->id,
            'period' => $period,
            'response_times' => $analytics['response_times'],
        ]);
    }

    /**
     * Get retry analysis
     */
    public function getRetryAnalysis(Request $request, Webhook $webhook): JsonResponse
    {
        $period = $request->query('period', '7d');
        $this->validatePeriod($period);

        $analytics = $this->analyticsService->getAnalytics($webhook, $period);

        return response()->json([
            'webhook_id' => $webhook->id,
            'period' => $period,
            'retry_analysis' => $analytics['retry_analysis'],
        ]);
    }

    /**
     * Get top errors
     */
    public function getTopErrors(Request $request, Webhook $webhook): JsonResponse
    {
        $period = $request->query('period', '7d');
        $this->validatePeriod($period);

        $analytics = $this->analyticsService->getAnalytics($webhook, $period);

        return response()->json([
            'webhook_id' => $webhook->id,
            'period' => $period,
            'errors' => $analytics['top_errors'],
        ]);
    }

    /**
     * Export analytics data
     */
    public function exportAnalytics(Request $request, Webhook $webhook): JsonResponse
    {
        $period = $request->query('period', '7d');
        $format = $request->query('format', 'json'); // json, csv

        $this->validatePeriod($period);

        $analytics = $this->analyticsService->getAnalytics($webhook, $period);

        if ($format === 'csv') {
            return response()->json([
                'message' => 'CSV export functionality would be implemented here',
                'format' => 'csv',
            ]);
        }

        return response()->json($analytics);
    }

    /**
     * Validate period parameter
     */
    protected function validatePeriod(string $period): void
    {
        $validPeriods = ['24h', '7d', '30d', '90d'];
        
        if (!in_array($period, $validPeriods)) {
            abort(400, "Invalid period: {$period}. Must be one of: " . implode(', ', $validPeriods));
        }
    }
}
