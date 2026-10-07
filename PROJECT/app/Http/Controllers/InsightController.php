<?php
namespace App\Http\Controllers;
use App\Models\DataInsight;
use App\Services\InsightGeneratorService;
use Illuminate\Http\Request;

class InsightController extends Controller
{
    public function __construct(protected InsightGeneratorService $generator)
    {
    }

    /**
     * PHASE 12: this endpoint didn't exist — DataInsight had a model and
     * a read-only controller, but nothing generated one. Same
     * "staff/cron-triggered, not a claimed automatic schedule" honesty
     * pattern as Phase 7's reminder sweeps.
     */
    public function generate(Request $request)
    {
        $insights = $this->generator->generate($request->user->tenant_id);

        return response()->json([
            'message' => count($insights) . ' insight(s) generated',
            'data' => $insights,
        ]);
    }

    public function list(Request $request)
    {
        $insights = DataInsight::where('tenant_id', $request->user->tenant_id)
            ->orderBy('generated_at', 'desc')
            ->paginate(20);
        return response()->json(['data' => $insights->items()]);
    }
    
    public function getByType(Request $request, $type)
    {
        $insights = DataInsight::where('tenant_id', $request->user->tenant_id)
            ->where('insight_type', $type)
            ->orderBy('generated_at', 'desc')
            ->limit(10)
            ->get();
        return response()->json(['data' => $insights]);
    }
    
    public function getTrending(Request $request)
    {
        $insights = DataInsight::where('tenant_id', $request->user->tenant_id)
            ->where('impact_level', 'high')
            ->orderBy('generated_at', 'desc')
            ->limit(5)
            ->get();
        return response()->json(['data' => $insights]);
    }
}
