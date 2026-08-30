<?php
namespace App\Http\Controllers;
use App\Models\TourPackage;
use Illuminate\Http\Request;

class TourController extends Controller
{
    public function index(Request $request)
    {
        $packages = TourPackage::where('tenant_id', $request->user->tenant_id)->paginate(20);
        return response()->json(['data' => $packages->items()]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'destination' => 'required|string',
            'duration_days' => 'required|integer',
            'price' => 'required|numeric',
            'max_capacity' => 'required|integer',
            'activities' => 'nullable|array',
        ]);
        $package = TourPackage::create(['tenant_id' => $request->user->tenant_id, 'status' => 'active', ...$validated]);
        return response()->json(['data' => $package], 201);
    }
    public function show(Request $request, $id)
    {
        $package = TourPackage::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        return response()->json(['data' => $package]);
    }

    public function update(Request $request, $id)
    {
        $package = TourPackage::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'destination' => 'sometimes|string',
            'duration_days' => 'sometimes|integer',
            'price' => 'sometimes|numeric',
            'max_capacity' => 'sometimes|integer',
            'activities' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive,sold_out',
        ]);
        $package->update($validated);
        return response()->json(['data' => $package]);
    }
}
