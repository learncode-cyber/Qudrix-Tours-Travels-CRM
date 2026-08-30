<?php
namespace App\Services;

use App\Models\Booking;
use App\Models\Communication;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FlightBooking;
use App\Models\HotelBooking;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Pilgrim;
use App\Models\Quotation;
use App\Models\StudentVisaApplication;
use App\Models\Task;
use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Every figure this service returns is computed from real rows with a real
// query. Nothing is stubbed, sampled, or estimated (Directive S27: "No
// fake/mock data in production features"; S3.A: "Dashboard must use REAL
// database data").
//
// Where a metric genuinely cannot be computed from what the system stores,
// it is returned as null with an explicit `unavailable_metrics` note rather
// than as a zero that would read as a real measurement.
class BehavioralAnalyticsService
{
    /**
     * Executive dashboard (Directive S3.A).
     */
    public function executiveDashboard(int $tenantId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to ??= now();

        $unavailable = [];

        // --- Revenue -----------------------------------------------------
        $totalRevenue = (float) Payment::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $outstanding = (float) Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->sum(DB::raw('total_amount - paid_amount'));

        $pendingPayments = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->count();

        // --- Leads & conversion -----------------------------------------
        $totalLeads = Lead::where('tenant_id', $tenantId)->whereBetween('created_at', [$from, $to])->count();
        $qualifiedLeads = Lead::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['qualified', 'proposal', 'negotiation', 'won'])
            ->count();
        $wonLeads = Lead::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'won')
            ->count();

        $conversionRate = $totalLeads > 0 ? round(($wonLeads / $totalLeads) * 100, 2) : null;
        if ($conversionRate === null) {
            $unavailable[] = 'conversion_rate (no leads created in the selected period)';
        }

        // --- Operations --------------------------------------------------
        $activeBookings = Booking::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'pending'])
            ->count();

        $todaysFollowUps = Lead::where('tenant_id', $tenantId)
            ->whereDate('follow_up_date', today())
            ->count();

        $upcomingDepartures = Booking::where('tenant_id', $tenantId)
            ->whereBetween('travel_date', [now(), now()->addDays(30)])
            ->count();

        return [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'revenue' => [
                'total_revenue' => round($totalRevenue, 2),
                'outstanding_amount' => round($outstanding, 2),
                'pending_payment_invoices' => $pendingPayments,
            ],
            'leads' => [
                'total_leads' => $totalLeads,
                'qualified_leads' => $qualifiedLeads,
                'won_leads' => $wonLeads,
                'conversion_rate_percent' => $conversionRate,
            ],
            'operations' => [
                'active_bookings' => $activeBookings,
                'todays_follow_ups' => $todaysFollowUps,
                'upcoming_departures_30d' => $upcomingDepartures,
                'visa_applications' => VisaApplication::where('tenant_id', $tenantId)->count(),
                'flight_bookings' => FlightBooking::whereHas('flight', fn ($q) => $q->where('tenant_id', $tenantId))->count(),
                'hotel_bookings' => HotelBooking::whereHas('hotel', fn ($q) => $q->where('tenant_id', $tenantId))->count(),
                'hajj_umrah_pilgrims' => Pilgrim::where('tenant_id', $tenantId)->count(),
                'student_visa_applications' => StudentVisaApplication::where('tenant_id', $tenantId)->count(),
            ],
            'sales_pipeline' => $this->salesPipeline($tenantId),
            'revenue_trend' => $this->revenueTrend($tenantId, 6),
            'lead_source_performance' => $this->leadSourcePerformance($tenantId, $from, $to),
            'staff_performance' => $this->staffPerformance($tenantId, $from, $to),
            'profit_and_loss' => $this->profitAndLoss($tenantId, $from, $to),
            'unavailable_metrics' => $unavailable,
        ];
    }

    /**
     * Lead counts and value by pipeline status — real GROUP BY.
     */
    public function salesPipeline(int $tenantId): array
    {
        return Lead::where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as lead_count, SUM(estimated_value) as total_value')
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => [
                'status' => $r->status,
                'lead_count' => (int) $r->lead_count,
                'total_estimated_value' => round((float) $r->total_value, 2),
            ])->all();
    }

    /**
     * Completed-payment revenue per month for the last N months.
     */
    public function revenueTrend(int $tenantId, int $months = 6): array
    {
        $since = now()->subMonths($months - 1)->startOfMonth();

        $rows = Payment::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $since)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, SUM(amount) as revenue, COUNT(*) as payment_count")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        // Fill gaps so a month with genuinely no revenue reads as 0 rather
        // than silently vanishing from the chart.
        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $period = now()->subMonths($months - 1 - $i)->format('Y-m');
            $row = $rows->get($period);
            $out[] = [
                'period' => $period,
                'revenue' => round((float) ($row->revenue ?? 0), 2),
                'payment_count' => (int) ($row->payment_count ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Which lead sources actually convert (Directive S12).
     */
    public function leadSourcePerformance(int $tenantId, Carbon $from, Carbon $to): array
    {
        return Lead::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("source,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won,
                SUM(estimated_value) as pipeline_value")
            ->groupBy('source')
            ->get()
            ->map(function ($r) {
                $total = (int) $r->total;
                $won = (int) $r->won;
                return [
                    'source' => $r->source,
                    'total_leads' => $total,
                    'won' => $won,
                    'conversion_rate_percent' => $total > 0 ? round(($won / $total) * 100, 2) : null,
                    'pipeline_value' => round((float) $r->pipeline_value, 2),
                ];
            })->all();
    }

    /**
     * Per-rep performance from real assigned leads, bookings and tasks.
     */
    public function staffPerformance(int $tenantId, Carbon $from, Carbon $to): array
    {
        $users = User::where('tenant_id', $tenantId)->get(['id', 'name']);

        return $users->map(function (User $user) use ($tenantId, $from, $to) {
            $assigned = Lead::where('tenant_id', $tenantId)
                ->where('assigned_to', $user->id)
                ->whereBetween('created_at', [$from, $to]);

            $assignedCount = (clone $assigned)->count();
            $wonCount = (clone $assigned)->where('status', 'won')->count();

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'leads_assigned' => $assignedCount,
                'leads_won' => $wonCount,
                'win_rate_percent' => $assignedCount > 0 ? round(($wonCount / $assignedCount) * 100, 2) : null,
                'bookings_created' => Booking::where('tenant_id', $tenantId)
                    ->where('created_by', $user->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->count(),
                'tasks_completed' => Task::where('tenant_id', $tenantId)
                    ->where('assigned_to', $user->id)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$from, $to])
                    ->count(),
            ];
        })->all();
    }

    /**
     * Real P&L: completed payments in, recorded expenses out.
     */
    public function profitAndLoss(int $tenantId, Carbon $from, Carbon $to): array
    {
        $income = (float) Payment::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        // expenses are dated by expense_date, not row creation time
        $expenses = (float) Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        return [
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'net' => round($income - $expenses, 2),
            'margin_percent' => $income > 0 ? round((($income - $expenses) / $income) * 100, 2) : null,
        ];
    }

    /**
     * Behavioural metrics (Directive S12): time-to-conversion, follow-up
     * effectiveness, engagement, deal value.
     */
    public function behavioralMetrics(int $tenantId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->subMonths(3)->startOfDay();
        $to ??= now();

        // Time to conversion: days between lead creation and its first
        // booking, averaged over leads that actually converted.
        $converted = Lead::where('leads.tenant_id', $tenantId)
            ->join('bookings', 'bookings.lead_id', '=', 'leads.id')
            ->whereBetween('leads.created_at', [$from, $to])
            ->selectRaw('leads.id, leads.created_at as lead_created, MIN(bookings.created_at) as first_booking')
            ->groupBy('leads.id', 'leads.created_at')
            ->get();

        $daysToConvert = $converted
            ->map(fn ($r) => Carbon::parse($r->lead_created)->diffInDays(Carbon::parse($r->first_booking)))
            ->filter(fn ($d) => $d !== null);

        $dealValues = Booking::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('total_amount')
            ->map(fn ($v) => (float) $v);

        // Follow-up effectiveness: of leads that were contacted at least
        // once, how many reached a won status.
        $contacted = Lead::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('last_contacted_at');
        $contactedCount = (clone $contacted)->count();
        $contactedWon = (clone $contacted)->where('status', 'won')->count();

        $engagement = Communication::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('type, COUNT(*) as total, SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) as read_count')
            ->groupBy('type')
            ->get()
            ->map(fn ($r) => [
                'channel' => $r->type,
                'messages' => (int) $r->total,
                'read' => (int) $r->read_count,
                'read_rate_percent' => (int) $r->total > 0 ? round(((int) $r->read_count / (int) $r->total) * 100, 2) : null,
            ])->all();

        return [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'time_to_conversion' => [
                'converted_leads' => $converted->count(),
                'average_days' => $daysToConvert->isNotEmpty() ? round($daysToConvert->avg(), 1) : null,
                'fastest_days' => $daysToConvert->isNotEmpty() ? $daysToConvert->min() : null,
                'slowest_days' => $daysToConvert->isNotEmpty() ? $daysToConvert->max() : null,
            ],
            'deal_value' => [
                'bookings' => $dealValues->count(),
                'average_value' => $dealValues->isNotEmpty() ? round($dealValues->avg(), 2) : null,
                'total_value' => round($dealValues->sum(), 2),
            ],
            'follow_up_effectiveness' => [
                'leads_contacted' => $contactedCount,
                'contacted_and_won' => $contactedWon,
                'win_rate_percent' => $contactedCount > 0 ? round(($contactedWon / $contactedCount) * 100, 2) : null,
            ],
            'engagement_by_channel' => $engagement,
            'customer_base' => [
                'total_customers' => Customer::where('tenant_id', $tenantId)->count(),
                'repeat_customers' => Booking::where('tenant_id', $tenantId)
                    ->select('customer_id')
                    ->groupBy('customer_id')
                    ->havingRaw('COUNT(*) > 1')
                    ->get()
                    ->count(),
            ],
        ];
    }

    /**
     * Quotation funnel — how many sent quotes actually convert.
     */
    public function quotationFunnel(int $tenantId, Carbon $from, Carbon $to): array
    {
        $rows = Quotation::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total, SUM(total_amount) as value')
            ->groupBy('status')
            ->get();

        return $rows->map(fn ($r) => [
            'status' => $r->status,
            'count' => (int) $r->total,
            'value' => round((float) $r->value, 2),
        ])->all();
    }
}
