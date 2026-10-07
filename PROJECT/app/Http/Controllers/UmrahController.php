<?php
namespace App\Http\Controllers;
use App\Models\UmrahPackage;
use Illuminate\Http\Request;

class UmrahController extends Controller
{
    public function index(Request $request)
    {
        $packages = UmrahPackage::where('tenant_id', $request->user->tenant_id)->paginate(20);
        return response()->json(['data' => $packages->items()]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'duration_days' => 'required|integer',
            'price' => 'required|numeric',
            'max_capacity' => 'required|integer',
            'rituals_included' => 'nullable|array',
        ]);
        $package = UmrahPackage::create(['tenant_id' => $request->user->tenant_id, 'status' => 'active', ...$validated]);
        return response()->json(['data' => $package], 201);
    }
    public function show(Request $request, $id)
    {
        $package = UmrahPackage::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        return response()->json(['data' => $package]);
    }

    // FIX (Phase 5 audit): UmrahController had no update() method at all
    // — HajjController's near-identical package model had one (albeit
    // unvalidated, also fixed this phase), but Umrah packages could
    // never be edited after creation.
    public function update(Request $request, $id)
    {
        $package = UmrahPackage::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'description' => 'nullable|string',
            'duration_days' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'max_capacity' => 'sometimes|integer|min:1',
            'rituals_included' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $package->update($validated);
        return response()->json(['data' => $package]);
    }

    public function getPackageStats(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        return response()->json(['data' => [
            'total_packages' => UmrahPackage::where('tenant_id', $tenantId)->count(),
            'active_packages' => UmrahPackage::where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'total_capacity' => UmrahPackage::where('tenant_id', $tenantId)->where('status', 'active')->sum('max_capacity'),
        ]]);
    }
}
