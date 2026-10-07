<?php
namespace App\Services;
use App\Models\Report;
use App\Models\ReportSchedule;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * PHASE 12 AUDIT FINDING: every generate*Report() method here previously
 * returned fully hardcoded fake data (e.g. 'total_bookings' => 150,
 * 'total_revenue' => 450000) regardless of any real record in the
 * database — the most severe "no fabricated metrics" violation found
 * across this entire project, since these numbers were specific and
 * plausible-looking rather than an obvious placeholder like 0. On top
 * of that, saveReportFile() never actually wrote a file — its own
 * comment said "In production: Storage::put(...)" while returning a
 * path to a file that was never created. Both are fixed below: every
 * report now queries real data (tenant-scoped), and the file is
 * genuinely written via Laravel's Storage facade. Metrics with no real
 * data source anywhere in this schema (satisfaction scores, customer
 * acquisition cost, profit margin — no cost/spend data exists) are
 * returned as null with an explanation, not invented.
 */
class ReportService
{
    public function generate(Report $report): array
    {
        $report->update(['status' => 'generating']);

        $tenantId = $report->tenant_id;
        $days = ($report->filters ?? [])['days'] ?? 30;
        $since = now()->subDays($days);

        $data = match($report->report_type) {
            'booking' => $this->generateBookingReport($tenantId, $since),
            'revenue' => $this->generateRevenueReport($tenantId, $since),
            'customer' => $this->generateCustomerReport($tenantId, $since),
            'travel' => $this->generateTravelReport($tenantId, $since),
            'performance' => $this->generatePerformanceReport($tenantId, $since),
            default => ['error' => "Unknown report_type '{$report->report_type}'"]
        };

        $filePath = $this->saveReportFile($report, $data);
        $report->update(['status' => 'completed', 'file_path' => $filePath, 'generated_at' => now()]);

        return ['report_id' => $report->id, 'status' => 'completed', 'data' => $data];
    }

    protected function generateBookingReport(int $tenantId, Carbon $since): array
    {
        $query = Booking::where('tenant_id', $tenantId)->where('created_at', '>=', $since);

        return [
            'total_bookings' => (clone $query)->count(),
            'confirmed_bookings' => (clone $query)->where('status', 'confirmed')->count(),
            'pending_bookings' => (clone $query)->where('status', 'pending')->count(),
            'cancelled_bookings' => (clone $query)->where('status', 'cancelled')->count(),
            'period' => "last_{$since->diffInDays(now())}_days",
        ];
    }

    protected function generateRevenueReport(int $tenantId, Carbon $since): array
    {
        $payments = Payment::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $since);

        $previousPeriodStart = $since->copy()->subDays($since->diffInDays(now()));
        $previousTotal = Payment::where('tenant_id', $tenantId)->where('status', 'completed')
            ->whereBetween('created_at', [$previousPeriodStart, $since])->sum('amount');
        $currentTotal = (clone $payments)->sum('amount');

        $revenueByType = Booking::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->selectRaw('booking_type, sum(total_amount) as total')
            ->groupBy('booking_type')
            ->pluck('total', 'booking_type');

        return [
            'total_revenue' => (float) $currentTotal,
            'revenue_by_booking_type' => $revenueByType,
            'revenue_growth_percent' => $previousTotal > 0 ? round((($currentTotal - $previousTotal) / $previousTotal) * 100, 1) : null,
        ];
    }

    protected function generateCustomerReport(int $tenantId, Carbon $since): array
    {
        $newCustomers = Customer::where('tenant_id', $tenantId)->where('created_at', '>=', $since)->count();
        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();
        $returning = Customer::where('tenant_id', $tenantId)->has('bookings', '>', 1)->count();

        return [
            'total_customers' => $totalCustomers,
            'new_customers' => $newCustomers,
            'returning_customers' => $returning,
            'customer_lifetime_value' => null,
            'churn_rate' => null,
            'unavailable_metrics' => [
                'customer_lifetime_value' => 'No customer-to-payment lifetime aggregation exists yet.',
                'churn_rate' => 'No defined "churned" status or inactivity threshold tracked in this schema yet.',
            ],
        ];
    }

    protected function generateTravelReport(int $tenantId, Carbon $since): array
    {
        $bookings = Booking::where('tenant_id', $tenantId)->where('created_at', '>=', $since);

        return [
            'total_travelers' => (clone $bookings)->sum('number_of_travelers'),
            'avg_group_size' => round((clone $bookings)->avg('number_of_travelers') ?? 0, 1),
            'satisfaction_score' => null,
            'unavailable_metrics' => [
                'satisfaction_score' => 'No customer feedback/survey data source exists in this schema yet.',
            ],
        ];
    }

    protected function generatePerformanceReport(int $tenantId, Carbon $since): array
    {
        $closedLeads = Lead::where('tenant_id', $tenantId)->whereIn('status', ['won', 'lost'])
            ->where('updated_at', '>=', $since)->count();
        $wonLeads = Lead::where('tenant_id', $tenantId)->where('status', 'won')
            ->where('updated_at', '>=', $since)->count();

        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();
        $repeatCustomers = Customer::where('tenant_id', $tenantId)->has('bookings', '>', 1)->count();

        return [
            'booking_conversion_rate' => $closedLeads > 0 ? round(($wonLeads / $closedLeads) * 100, 1) : null,
            'avg_booking_value' => round(Booking::where('tenant_id', $tenantId)->avg('total_amount') ?? 0, 2),
            'repeat_booking_rate' => $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : null,
            'customer_acquisition_cost' => null,
            'profit_margin' => null,
            'unavailable_metrics' => [
                'customer_acquisition_cost' => 'No marketing spend data tracked anywhere in this schema.',
                'profit_margin' => 'No cost data exists on bookings (only sell price) to compute margin against.',
            ],
        ];
    }

    protected function saveReportFile(Report $report, array $data): string
    {
        $filename = "report_{$report->id}_" . now()->timestamp . ".json";
        $path = "reports/{$filename}";

        // FIX (Phase 12 audit): this previously never actually wrote
        // anything — Storage::put() was commented out while file_path
        // was saved to the database as if the file existed.
        Storage::put($path, json_encode($data, JSON_PRETTY_PRINT));

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
