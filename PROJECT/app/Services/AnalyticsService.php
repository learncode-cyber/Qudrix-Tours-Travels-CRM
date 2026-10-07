<?php
namespace App\Services;
use App\Models\Analytics;
use App\Models\Booking;
use App\Models\Customer;
use Carbon\Carbon;

class AnalyticsService
{
    public function recordMetric(int $tenantId, string $type, mixed $value, string $period = 'daily'): void
    {
        Analytics::create([
            'tenant_id' => $tenantId,
            'metric_type' => $type,
            'metric_value' => $value,
            'period' => $period,
            'recorded_date' => now()
        ]);
    }
    
    public function getRevenueTrend(int $tenantId, int $days = 30): array
    {
        $data = Analytics::where('tenant_id', $tenantId)
            ->where('metric_type', 'revenue')
            ->where('recorded_date', '>=', now()->subDays($days))
            ->orderBy('recorded_date')
            ->get()
            ->groupBy(function($item) {
                return $item->recorded_date->format('Y-m-d');
            })
            ->map->sum('metric_value');
        
        return $data->toArray();
    }
    
    public function getBookingMetrics(int $tenantId): array
    {
        // FIX (Phase 12 audit): this previously returned literal hardcoded
        // numbers ('total_this_month' => 50, etc.) regardless of any real
        // data — a direct violation of the "no fabricated metrics" rule,
        // and worse than an honest zero because it looked plausible.
        $thisMonth = Booking::where('tenant_id', $tenantId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);

        return [
            'total_this_month' => (clone $thisMonth)->count(),
            'completed' => (clone $thisMonth)->where('status', 'completed')->count(),
            'pending' => (clone $thisMonth)->where('status', 'pending')->count(),
            'cancelled' => (clone $thisMonth)->where('status', 'cancelled')->count(),
            'avg_value' => (clone $thisMonth)->avg('total_amount'),
        ];
    }

    public function getCustomerMetrics(int $tenantId): array
    {
        // FIX (Phase 12 audit): same issue — was hardcoded
        // ('total_customers' => 500, etc.). Replaced with real queries.
        // 'retention_rate' and 'avg_lifetime_value' are left out rather
        // than faked: retention needs a defined cohort window this
        // schema doesn't track yet, and lifetime value needs a
        // customer-to-payment aggregation this app doesn't compute
        // anywhere else either — reporting null for both is the honest
        // answer, not an invented percentage.
        return [
            'total_customers' => Customer::where('tenant_id', $tenantId)->count(),
            'new_this_month' => Customer::where('tenant_id', $tenantId)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'active_this_month' => Booking::where('tenant_id', $tenantId)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->distinct('customer_id')
                ->count('customer_id'),
            'retention_rate' => null,
            'avg_lifetime_value' => null,
            'unavailable_metrics' => [
                'retention_rate' => 'No defined cohort/retention window tracked in this schema yet.',
                'avg_lifetime_value' => 'No customer-to-payment lifetime aggregation exists yet.',
            ],
        ];
    }
}
