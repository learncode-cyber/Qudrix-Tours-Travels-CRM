<?php
namespace App\Http\Controllers;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected $reportService;
    public function __construct(ReportService $reportService) { $this->reportService = $reportService; }
    
    public function index(Request $request)
    {
        $reports = Report::where('tenant_id', $request->user->tenant_id)
            ->paginate(20);
        return response()->json(['data' => $reports->items()]);
    }
    
    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'report_type' => 'required|in:booking,revenue,customer,travel,performance',
            'filters' => 'nullable|array'
        ]);
        $report = Report::create(['tenant_id' => $request->user->tenant_id, 'status' => 'generating', ...$validated]);
        return response()->json(['data' => $report], 201);
    }
    
    public function generate(Request $request, $id)
    {
        $report = Report::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $result = $this->reportService->generate($report);
        return response()->json(['data' => $result]);
    }
    
    public function schedule(Request $request, $id)
    {
        $report = Report::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $this->reportService->scheduleReport($report, $request->input('frequency'), $request->input('recipients', []));
        return response()->json(['message' => 'Report scheduled']);
    }
}
