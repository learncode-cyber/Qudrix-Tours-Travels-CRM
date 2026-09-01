<?php
namespace App\Services;
use App\Models\Report;
use App\Models\ReportSchedule;
use Carbon\Carbon;

class ReportService
{
    public function generate(Report $report): array
    {
        $report->update(['status' => 'generating']);
        
        $data = match($report->report_type) {
            'booking' => $this->generateBookingReport($report),
            'revenue' => $this->generateRevenueReport($report),
            'customer' => $this->generateCustomerReport($report),
            'travel' => $this->generateTravelReport($report),
            'performance' => $this->generatePerformanceReport($report),
            default => []
        };
        
        $filePath = $this->saveReportFile($report, $data);
        $report->update(['status' => 'completed', 'file_path' => $filePath, 'generated_at' => now()]);
        
        return ['report_id' => $report->id, 'status' => 'completed', 'data' => $data];
    }
    
    protected function generateBookingReport(Report $report): array
    {
        return [
            'total_bookings' => 150,
            'confirmed_bookings' => 120,
            'pending_bookings' => 20,
            'cancelled_bookings' => 10,
            'period' => 'last_30_days'
        ];
    }
    
    protected function generateRevenueReport(Report $report): array
    {
        return [
            'total_revenue' => 450000,
            'revenue_by_source' => ['flights' => 200000, 'hotels' => 150000, 'tours' => 100000],
            'revenue_growth' => 15.5,
            'top_destinations' => ['Dubai' => 120000, 'Mecca' => 100000, 'Istanbul' => 80000]
        ];
    }
    
    protected function generateCustomerReport(Report $report): array
    {
        return [
            'total_customers' => 500,
            'new_customers' => 45,
            'returning_customers' => 455,
            'customer_lifetime_value' => 900,
            'churn_rate' => 2.5
        ];
    }
    
    protected function generateTravelReport(Report $report): array
    {
        return [
            'total_travelers' => 1200,
            'top_destinations' => ['Saudi Arabia' => 400, 'Turkey' => 300, 'UAE' => 300, 'Egypt' => 200],
            'avg_group_size' => 4.5,
            'satisfaction_score' => 4.6
        ];
    }
    
    protected function generatePerformanceReport(Report $report): array
    {
        return [
            'booking_conversion_rate' => 22.5,
            'avg_booking_value' => 3000,
            'repeat_booking_rate' => 65,
            'customer_acquisition_cost' => 150,
            'profit_margin' => 28
        ];
    }
    
    protected function saveReportFile(Report $report, array $data): string
    {
        $filename = "report_{$report->id}_" . now()->timestamp . ".json";
        $path = "reports/{$filename}";
        // In production: Storage::put($path, json_encode($data));
        return $path;
    }
    
    public function scheduleReport(Report $report, string $frequency, array $recipients): ReportSchedule
    {
        return ReportSchedule::create([
            'report_id' => $report->id,
            'frequency' => $frequency,
            'recipients' => $recipients,
            'next_run_at' => $this->calculateNextRun($frequency),
            'is_active' => true
        ]);
    }
    
    protected function calculateNextRun(string $frequency): Carbon
    {
        return match($frequency) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            default => now()->addDay()
        };
    }
}
