<?php
namespace App\Http\Controllers;
use App\Models\Automation;
use App\Models\AutomationLog;
use Illuminate\Http\Request;

class AutomationLogController extends Controller
{
    public function getAutomationLogs(Request $request, $automationId)
    {
        Automation::where('tenant_id', $request->user->tenant_id)->findOrFail($automationId);
        $logs = AutomationLog::where('automation_id', $automationId)
            ->orderBy('started_at', 'desc')
            ->paginate(50);
        return response()->json(['data' => $logs->items()]);
    }
    
    public function getStats(Request $request, $automationId)
    {
        Automation::where('tenant_id', $request->user->tenant_id)->findOrFail($automationId);
        $logs = AutomationLog::where('automation_id', $automationId)->get();
        $stats = [
            'total_runs' => $logs->count(),
            'success_count' => $logs->where('status', 'success')->count(),
            'error_count' => $logs->where('status', 'error')->count(),
            'avg_execution_time_ms' => (int) $logs->avg('execution_time_ms'),
        ];
        return response()->json(['data' => $stats]);
    }
    
    public function clearLogs(Request $request, $automationId)
    {
        Automation::where('tenant_id', $request->user->tenant_id)->findOrFail($automationId);
        AutomationLog::where('automation_id', $automationId)->delete();
        return response()->json(['message' => 'Logs cleared']);
    }
}
