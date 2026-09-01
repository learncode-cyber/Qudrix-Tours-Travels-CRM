<?php
namespace App\Http\Controllers;
use App\Models\DataInsight;
use Illuminate\Http\Request;

class InsightController extends Controller
{
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
