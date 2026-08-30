<?php
namespace App\Http\Controllers;
use App\Models\Dashboard;
use App\Models\Analytics;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function getDefault(Request $request)
    {
        $dashboard = Dashboard::where('tenant_id', $request->user->tenant_id)
            ->where('user_id', $request->user->id)
            ->where('is_default', true)
            ->firstOrCreate([
                'tenant_id' => $request->user->tenant_id,
                'user_id' => $request->user->id,
                'name' => 'Default Dashboard',
                'widgets' => ['revenue', 'bookings', 'customers', 'performance'],
                'is_default' => true
            ]);
        return response()->json(['data' => $dashboard]);
    }
    
    public function update(Request $request, $id)
    {
        $dashboard = Dashboard::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'widgets' => 'sometimes|array',
            'layout' => 'nullable|array',
            'is_default' => 'boolean',
        ]);
        $dashboard->update($validated);
        return response()->json(['data' => $dashboard]);
    }
    
    // Previously returned a hardcoded zero for every KPI regardless of the
    // data — a "Real Data Only" violation (Directive §27). Now computed
    // from real rows. Metrics the system genuinely does not capture are
    // reported as null with a reason, never as a zero that would read as a
    // real measurement.
    public function getKPI(Request $request, \App\Services\BehavioralAnalyticsService $analytics)
    {
        $tenantId = $request->user->tenant_id;
        $from = $request->from ? now()->parse($request->from) : now()->startOfMonth();
        $to = $request->to ? now()->parse($request->to) : now();

        $bookings = \App\Models\Booking::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to]);
        $totalBookings = (clone $bookings)->count();
        $bookingValue = (float) (clone $bookings)->sum('total_amount');

        $pnl = $analytics->profitAndLoss($tenantId, $from, $to);

        return response()->json(['data' => [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'total_bookings' => $totalBookings,
            'total_revenue' => $pnl['income'],
            'total_customers' => \App\Models\Customer::where('tenant_id', $tenantId)->count(),
            'avg_booking_value' => $totalBookings > 0 ? round($bookingValue / $totalBookings, 2) : null,
            // Not tracked by this system: there is no survey/CSAT capture and
            // no room-inventory occupancy model. Reported as null rather than
            // fabricated.
            'customer_satisfaction' => null,
            'occupancy_rate' => null,
            'unavailable_metrics' => [
                'customer_satisfaction (no CSAT/survey data is captured by this system)',
                'occupancy_rate (requires per-date room inventory tracking, not yet modelled)',
            ],
        ]]);
    }
}
