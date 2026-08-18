<?php
namespace App\Http\Controllers;
use App\Models\AutomationDashboard;
use App\Models\Automation;
use App\Models\AutomationLog;
use Illuminate\Http\Request;

class AutomationDashboardController extends Controller
{
    public function getSummary(Request $request)
    {
        $automations = Automation::where('tenant_id', $request->user->tenant_id)->get();
        $totalAutomations = $automations->count();
        $activeAutomations = $automations->where('is_active', true)->count();
        $totalRuns = AutomationLog::whereIn('automation_id', $automations->pluck('id'))->count();
        
        $recentLogs = AutomationLog::whereIn('automation_id', $automations->pluck('id'))
            ->orderBy('started_at', 'desc')
            ->limit(10)
            ->get();
        
        return response()->json(['data' => [
            'total_automations' => $totalAutomations,
            'active_automations' => $activeAutomations,
            'total_runs' => $totalRuns,
            'recent_executions' => $recentLogs
        ]]);
    }
    
    public function getMetrics(Request $request)
    {
        $automations = Automation::where('tenant_id', $request->user->tenant_id)->pluck('id');
        $logs = AutomationLog::whereIn('automation_id', $automations)->get();
        
        $metrics = [
            'success_rate' => $logs->count() > 0 ? 
                ($logs->where('status', 'success')->count() / $logs->count() * 100) : 0,
            'error_rate' => $logs->count() > 0 ? 
                ($logs->where('status', 'error')->count() / $logs->count() * 100) : 0,
            'avg_execution_time' => (int) $logs->avg('execution_time_ms'),
            'peak_hour' => $logs->groupBy(function($log) { 
                return $log->started_at->format('H:00'); 
            })->map->count()->sortDesc()->keys()->first(),
        ];
        
        return response()->json(['data' => $metrics]);
    }
}
