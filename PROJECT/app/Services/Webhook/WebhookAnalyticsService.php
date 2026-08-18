<?php

namespace App\Services\Webhook;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WebhookAnalyticsService
{
    /**
     * Get comprehensive webhook analytics
     */
    public function getAnalytics(Webhook $webhook, string $period = '7d'): array
    {
        $startDate = $this->getStartDate($period);

        return [
            'webhook_id' => $webhook->id,
            'webhook_name' => $webhook->name,
            'period' => $period,
            'summary' => $this->getSummary($webhook, $startDate),
            'daily_stats' => $this->getDailyStats($webhook, $startDate),
            'event_breakdown' => $this->getEventBreakdown($webhook, $startDate),
            'success_rate_trend' => $this->getSuccessRateTrend($webhook, $startDate),
            'response_times' => $this->getResponseTimesStats($webhook, $startDate),
            'retry_analysis' => $this->getRetryAnalysis($webhook, $startDate),
            'top_errors' => $this->getTopErrors($webhook, $startDate),
            'generated_at' => now(),
        ];
    }

    /**
     * Get summary statistics
     */
    protected function getSummary(Webhook $webhook, Carbon $startDate): array
    {
        $deliveries = $webhook->deliveries()
            ->where('created_at', '>=', $startDate)
            ->get();

        $delivered = $deliveries->where('status', 'delivered')->count();
        $failed = $deliveries->where('status', 'failed')->count();
        $pending = $deliveries->where('status', 'pending')->count();
        $total = $deliveries->count();

        $avgResponseTime = $this->getAverageResponseTime($deliveries);

        return [
            'total_deliveries' => $total,
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => $pending,
            'success_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
            'average_response_time' => round($avgResponseTime, 2),
        ];
    }

    /**
     * Get daily statistics
     */
    protected function getDailyStats(Webhook $webhook, Carbon $startDate): array
    {
        $stats = [];
        $currentDate = $startDate->copy();
        $endDate = now();

        while ($currentDate->lte($endDate)) {
            $dayDeliveries = $webhook->deliveries()
                ->whereDate('created_at', $currentDate->toDateString())
                ->get();

            $delivered = $dayDeliveries->where('status', 'delivered')->count();
            $failed = $dayDeliveries->where('status', 'failed')->count();
            $total = $dayDeliveries->count();

            $stats[$currentDate->toDateString()] = [
                'date' => $currentDate->toDateString(),
                'total' => $total,
                'delivered' => $delivered,
                'failed' => $failed,
                'success_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
            ];

            $currentDate->addDay();
        }

        return $stats;
    }

    /**
     * Get event type breakdown
     */
    protected function getEventBreakdown(Webhook $webhook, Carbon $startDate): array
    {
        return $webhook->deliveries()
            ->where('created_at', '>=', $startDate)
            ->groupBy('event_type')
            ->select(
                'event_type',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            )
            ->get()
            ->map(function ($event) {
                return [
                    'event_type' => $event->event_type,
                    'total' => $event->total,
                    'delivered' => $event->delivered,
                    'failed' => $event->failed,
                    'success_rate' => $event->total > 0 
                        ? round(($event->delivered / $event->total) * 100, 2) 
                        : 0,
                ];
            })
            ->toArray();
    }

    /**
     * Get success rate trend
     */
    protected function getSuccessRateTrend(Webhook $webhook, Carbon $startDate): array
    {
        $stats = DB::table('webhook_deliveries')
            ->where('webhook_id', $webhook->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered")
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return $stats->map(function ($stat) {
            return [
                'date' => $stat->date,
                'success_rate' => $stat->total > 0 
                    ? round(($stat->delivered / $stat->total) * 100, 2) 
                    : 0,
            ];
        })->toArray();
    }

    /**
     * Get response time statistics
     */
    protected function getResponseTimesStats(Webhook $webhook, Carbon $startDate): array
    {
        $deliveries = $webhook->deliveries()
            ->where('created_at', '>=', $startDate)
            ->where('status', 'delivered')
            ->get()
            ->map(function ($delivery) {
                return $this->calculateResponseTime($delivery);
            })
            ->filter()
            ->values();

        if ($deliveries->isEmpty()) {
            return [
                'average' => 0,
                'min' => 0,
                'max' => 0,
                'median' => 0,
                'p95' => 0,
                'p99' => 0,
            ];
        }

        $sorted = $deliveries->sort()->values();

        return [
            'average' => round($sorted->avg(), 2),
            'min' => round($sorted->min(), 2),
            'max' => round($sorted->max(), 2),
            'median' => round($sorted->median(), 2),
            'p95' => round($sorted->slice(floor(count($sorted) * 0.95))->first() ?? 0, 2),
            'p99' => round($sorted->slice(floor(count($sorted) * 0.99))->first() ?? 0, 2),
        ];
    }

    /**
     * Get retry analysis
     */
    protected function getRetryAnalysis(Webhook $webhook, Carbon $startDate): array
    {
        $deliveries = $webhook->deliveries()
            ->where('created_at', '>=', $startDate)
            ->get();

        $deliveriesWithRetries = $deliveries->filter(fn($d) => $d->retry_count > 0);

        return [
            'total_retried' => $deliveriesWithRetries->count(),
            'percentage_retried' => $deliveries->count() > 0 
                ? round(($deliveriesWithRetries->count() / $deliveries->count()) * 100, 2) 
                : 0,
            'average_retries_per_delivery' => $deliveries->count() > 0 
                ? round($deliveries->sum('retry_count') / $deliveries->count(), 2) 
                : 0,
            'retry_distribution' => $this->getRetryDistribution($deliveries),
        ];
    }

    /**
     * Get retry distribution
     */
    protected function getRetryDistribution(Collection $deliveries): array
    {
        $distribution = [];
        
        for ($i = 0; $i <= 5; $i++) {
            $count = $deliveries->where('retry_count', $i)->count();
            $distribution[$i] = [
                'retry_count' => $i,
                'deliveries' => $count,
                'percentage' => $deliveries->count() > 0 
                    ? round(($count / $deliveries->count()) * 100, 2) 
                    : 0,
            ];
        }

        return array_values($distribution);
    }

    /**
     * Get top errors
     */
    protected function getTopErrors(Webhook $webhook, Carbon $startDate): array
    {
        return DB::table('webhook_logs')
            ->where('webhook_id', $webhook->id)
            ->where('created_at', '>=', $startDate)
            ->where('error_message', '!=', null)
            ->select('error_message')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('error_message')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($error) {
                return [
                    'error' => $error->error_message,
                    'count' => $error->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get average response time
     */
    protected function getAverageResponseTime(Collection $deliveries): float
    {
        $responseTimes = $deliveries
            ->map(fn($d) => $this->calculateResponseTime($d))
            ->filter()
            ->values();

        return $responseTimes->isEmpty() ? 0 : $responseTimes->avg();
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

    /**
     * Get start date based on period
     */
    protected function getStartDate(string $period): Carbon
    {
        return match ($period) {
            '24h' => now()->subHours(24),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(7),
        };
    }
}
