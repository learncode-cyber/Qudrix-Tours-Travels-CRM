<?php
namespace App\Http\Controllers;
use App\Models\CachePolicy;
use App\Services\CacheService;
use Illuminate\Http\Request;

class CacheController extends Controller
{
    protected $cacheService;
    public function __construct(CacheService $cacheService) { $this->cacheService = $cacheService; }
    
    public function getCachePolicies(Request $request)
    {
        $policies = CachePolicy::where('tenant_id', $request->user->tenant_id)
            ->where('is_active', true)
            ->get();
        return response()->json(['data' => $policies]);
    }
    
    public function createPolicy(Request $request)
    {
        $validated = $request->validate([
            'resource_type' => 'required|string',
            'cache_strategy' => 'required|in:network_first,cache_first,stale_while_revalidate',
            'ttl_minutes' => 'required|integer',
            'max_size_mb' => 'required|integer',
            'priority' => 'nullable|in:low,medium,high'
        ]);
        $policy = CachePolicy::create(['tenant_id' => $request->user->tenant_id, ...$validated]);
        return response()->json(['data' => $policy], 201);
    }
    
    public function clearCache(Request $request)
    {
        $cleared = $this->cacheService->clearTenantCache($request->user->tenant_id);
        return response()->json(['data' => ['cleared_items' => $cleared]]);
    }
    
    public function getCacheStats(Request $request)
    {
        $stats = $this->cacheService->getCacheStats($request->user->tenant_id);
        return response()->json(['data' => $stats]);
    }
}
