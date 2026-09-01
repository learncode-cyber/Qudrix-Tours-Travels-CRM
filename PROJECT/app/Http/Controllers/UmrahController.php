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
}
