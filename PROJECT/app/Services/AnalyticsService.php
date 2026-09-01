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
        return [
            'total_this_month' => 50,
            'completed' => 40,
            'pending' => 8,
            'cancelled' => 2,
            'avg_value' => 3500
        ];
    }
    
    public function getCustomerMetrics(int $tenantId): array
    {
        return [
            'total_customers' => 500,
            'new_this_month' => 45,
            'active_this_month' => 120,
            'retention_rate' => 85,
            'avg_lifetime_value' => 8500
        ];
    }
}
