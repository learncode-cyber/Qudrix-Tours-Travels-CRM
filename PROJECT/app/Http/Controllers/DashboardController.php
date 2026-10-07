<?php
namespace App\Http\Controllers;
use App\Models\Dashboard;
use App\Models\Analytics;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Booking;
use App\Models\Payment;
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

        // FIX (frontend integration audit, 2026-09-21): same mass-assignment
        // gap as AutomationController@update — Dashboard's $fillable
        // includes both 'tenant_id' and 'user_id', so raw $request->all()
        // would have let any caller reassign a dashboard to a different
        // tenant or a different user's account.
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'widgets' => 'sometimes|array',
            'layout' => 'nullable|array',
            'is_default' => 'sometimes|boolean',
        ]);

        $dashboard->update($validated);
        return response()->json(['data' => $dashboard]);
    }
    
    public function getKPI(Request $request)
    {
        // FIX (Phase 2 audit): this previously returned a hardcoded stub
        // (every value literally 0), which violates the project's own
        // "zero fake implementation" rule — it looked like a working KPI
        // endpoint but could never reflect real data. Replaced with real
        // aggregate queries against tables that exist. Two of the original
        // stub's fields (customer_satisfaction, occupancy_rate) have no
        // backing data source anywhere in this schema yet, so they're
        // reported as null with an explanatory note rather than a fake
        // number — do not invent a plausible-looking value for these.
        $tenantId = $request->user->tenant_id;

        $totalBookings = Booking::where('tenant_id', $tenantId)->count();
        $totalRevenue = Payment::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->sum('amount');

        $kpis = [
            'total_bookings' => $totalBookings,
            'total_revenue' => (float) $totalRevenue,
            'total_customers' => Customer::where('tenant_id', $tenantId)->count(),
            'total_leads' => Lead::where('tenant_id', $tenantId)->count(),
            'avg_booking_value' => $totalBookings > 0
                ? round(Booking::where('tenant_id', $tenantId)->avg('total_amount'), 2)
                : 0,
            'customer_satisfaction' => null,
            'occupancy_rate' => null,
            'unavailable_metrics' => [
                'customer_satisfaction' => 'No CSAT/feedback data source exists in the schema yet.',
                'occupancy_rate' => 'No hotel room-inventory model exists in the schema yet.',
            ],
        ];

        return response()->json(['data' => $kpis]);
    }
}
