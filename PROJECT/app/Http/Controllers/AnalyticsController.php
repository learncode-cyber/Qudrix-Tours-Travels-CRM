<?php
namespace App\Http\Controllers;
use App\Models\Analytics;
use App\Models\Lead;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function getMetrics(Request $request)
    {
        $period = $request->input('period', 'daily');
        $metrics = Analytics::where('tenant_id', $request->user->tenant_id)
            ->where('period', $period)
            ->orderBy('recorded_date', 'desc')
            ->limit(30)
            ->get()
            ->groupBy('metric_type');
        return response()->json(['data' => $metrics]);
    }
    
    public function getMetricByType(Request $request, $metricType)
    {
        $data = Analytics::where('tenant_id', $request->user->tenant_id)
            ->where('metric_type', $metricType)
            ->orderBy('recorded_date', 'desc')
            ->limit(100)
            ->get();
        return response()->json(['data' => $data]);
    }

    /**
     * PHASE 12: Lead funnel + drop-off — real counts per stage, and the
     * conversion rate between each consecutive stage, so a genuine
     * drop-off point is visible rather than just a total.
     */
    public function leadFunnel(Request $request)
    {
        $tenantId = $request->user->tenant_id;
        $stages = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won'];

        $counts = [];
        foreach ($stages as $stage) {
            $counts[$stage] = Lead::where('tenant_id', $tenantId)->where('status', $stage)->count();
        }

        $dropoff = [];
        for ($i = 0; $i < count($stages) - 1; $i++) {
            $from = $counts[$stages[$i]];
            $to = $counts[$stages[$i + 1]];
            $dropoff[] = [
                'from' => $stages[$i],
                'to' => $stages[$i + 1],
                'rate' => $from > 0 ? round(($to / $from) * 100, 1) : null,
            ];
        }

        return response()->json([
            'data' => [
                'stage_counts' => $counts,
                'lost' => Lead::where('tenant_id', $tenantId)->where('status', 'lost')->count(),
                'stage_to_stage_conversion' => $dropoff,
            ],
        ]);
    }

    public function conversionRate(Request $request)
    {
        $tenantId = $request->user->tenant_id;
        $closed = Lead::where('tenant_id', $tenantId)->whereIn('status', ['won', 'lost'])->count();
        $won = Lead::where('tenant_id', $tenantId)->where('status', 'won')->count();

        return response()->json(['data' => [
            'won' => $won,
            'lost' => $closed - $won,
            'overall_conversion_rate' => $closed > 0 ? round(($won / $closed) * 100, 1) : null,
        ]]);
    }

    public function dealValue(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $wonLeadIds = Lead::where('tenant_id', $tenantId)->where('status', 'won')->pluck('id');
        $avgBookingValue = Booking::where('tenant_id', $tenantId)->avg('total_amount');

        return response()->json(['data' => [
            'won_deals' => $wonLeadIds->count(),
            'avg_estimated_value_of_won_deals' => Lead::whereIn('id', $wonLeadIds)->avg('estimated_value'),
            'avg_booking_value' => $avgBookingValue !== null ? round($avgBookingValue, 2) : null,
        ]]);
    }

    /**
     * PHASE 12: agent (staff) performance — real per-assignee counts.
     * Deliberately doesn't rank/score agents against each other beyond
     * raw counts — a fairness-sensitive judgment call the spec doesn't
     * ask this app to make automatically.
     */
    public function salesPerformance(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $performance = Lead::where('tenant_id', $tenantId)
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, count(*) as total_leads, sum(case when status = "won" then 1 else 0 end) as won')
            ->groupBy('assigned_to')
            ->with('assignedTo:id,name')
            ->get()
            ->map(fn ($row) => [
                'user_id' => $row->assigned_to,
                'name' => $row->assignedTo->name ?? null,
                'total_leads' => $row->total_leads,
                'won' => $row->won,
                'conversion_rate' => $row->total_leads > 0 ? round(($row->won / $row->total_leads) * 100, 1) : null,
            ]);

        return response()->json(['data' => $performance]);
    }

    public function revenueAnalytics(Request $request)
    {
        $tenantId = $request->user->tenant_id;
        $days = (int) ($request->days ?? 30);

        $daily = Payment::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as day, sum(amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->json(['data' => [
            'daily_revenue' => $daily,
            'total_in_period' => $daily->sum('total'),
        ]]);
    }

    /**
     * PHASE 12: forecasting — deliberately a simple, explainable moving
     * average of recent daily revenue, NOT a claimed ML model. Returns
     * null with an explanation if there isn't enough history yet, rather
     * than projecting from too little data.
     */
    public function revenueForecast(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $daily = Payment::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as day, sum(amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total');

        if ($daily->count() < 7) {
            return response()->json(['data' => [
                'forecast_next_7_days' => null,
                'method' => 'simple_moving_average',
                'note' => 'Insufficient history (fewer than 7 days of completed payments) to forecast responsibly.',
            ]]);
        }

        $avgDaily = $daily->avg();

        return response()->json(['data' => [
            'forecast_next_7_days' => round($avgDaily * 7, 2),
            'basis' => 'Average of last ' . $daily->count() . ' days with completed payments',
            'method' => 'simple_moving_average',
            'note' => 'This is a naive projection (recent average x 7), not a statistical/ML forecast — treat as a rough directional estimate only.',
        ]]);
    }
}
