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
        $dashboard->update($request->all());
        return response()->json(['data' => $dashboard]);
    }
    
    public function getKPI(Request $request)
    {
        $kpis = [
            'total_bookings' => 0,
            'total_revenue' => 0,
            'total_customers' => 0,
            'avg_booking_value' => 0,
            'customer_satisfaction' => 0,
            'occupancy_rate' => 0
        ];
        return response()->json(['data' => $kpis]);
    }
}
