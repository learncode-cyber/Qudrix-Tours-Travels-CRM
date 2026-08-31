<?php
namespace App\Http\Controllers;
use App\Models\HajjPackage;
use Illuminate\Http\Request;

class HajjController extends Controller
{
    public function index(Request $request)
    {
        $packages = HajjPackage::where('tenant_id', $request->user->tenant_id)->paginate(20);
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
            'accommodations' => 'nullable|array',
        ]);
        $package = HajjPackage::create(['tenant_id' => $request->user->tenant_id, 'status' => 'active', ...$validated]);
        return response()->json(['data' => $package], 201);
    }
    public function show(Request $request, $id)
    {
        $package = HajjPackage::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        return response()->json(['data' => $package]);
    }
    public function update(Request $request, $id)
    {
        $package = HajjPackage::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'description' => 'nullable|string',
            'duration_days' => 'sometimes|integer',
            'price' => 'sometimes|numeric',
            'currency' => 'sometimes|string|size:3',
            'max_capacity' => 'sometimes|integer',
            'rituals_included' => 'nullable|array',
            'accommodations' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive,discontinued',
        ]);
        $package->update($validated);
        return response()->json(['data' => $package]);
    }
}
