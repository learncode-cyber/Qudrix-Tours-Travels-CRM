<?php
namespace App\Http\Controllers;
use App\Models\CustomerSegment;
use App\Services\SegmentationService;
use Illuminate\Http\Request;

class SegmentController extends Controller
{
    protected $segmentationService;
    public function __construct(SegmentationService $segmentationService) { $this->segmentationService = $segmentationService; }
    
    public function list(Request $request)
    {
        $segments = CustomerSegment::where('tenant_id', $request->user->tenant_id)
            ->paginate(20);
        return response()->json(['data' => $segments->items()]);
    }
    
    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'criteria' => 'required|array',
            'description' => 'nullable|string'
        ]);
        $segment = CustomerSegment::create(['tenant_id' => $request->user->tenant_id, 'status' => 'active', ...$validated]);
        $count = $this->segmentationService->countMembers($segment);
        $segment->update(['member_count' => $count]);
        return response()->json(['data' => $segment], 201);
    }
    
    public function getMembers(Request $request, $id)
    {
        $segment = CustomerSegment::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $members = $this->segmentationService->getSegmentMembers($segment);
        return response()->json(['data' => $members]);
    }
}
