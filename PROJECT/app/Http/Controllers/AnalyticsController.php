<?php
namespace App\Http\Controllers;
use App\Models\Analytics;
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
}
