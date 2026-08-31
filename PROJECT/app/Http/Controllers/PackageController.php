<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;

// Basic package CRUD. Distinct from PackageBuilderController/
// AiPackageBuilderController (which construct a Package from a
// quotation or an AI-assisted flow) — this is the plain "staff creates
// and manages standard tour packages" path that Booking::package_id,
// QuotationItem::package_id, and the whole booking UI depend on, and
// which had no endpoint at all until this phase.
class PackageController extends Controller
{
    public function index(Request $request)
    {
        $query = Package::where('tenant_id', $request->user->tenant_id);

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->is_active !== null && $request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $packages = $query->orderBy('name')->paginate($request->per_page ?? 50);

        return response()->json([
            'data' => $packages->items(),
            'pagination' => [
                'total' => $packages->total(),
                'per_page' => $packages->perPage(),
                'current_page' => $packages->currentPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'days' => 'nullable|integer|min:0',
            'nights' => 'nullable|integer|min:0',
            'destination' => 'nullable|string|max:255',
            'base_price' => 'nullable|numeric|min:0',
            'inclusions' => 'nullable|array',
            'exclusions' => 'nullable|array',
        ]);

        $package = Package::create([
            'tenant_id' => $request->user->tenant_id,
            'is_active' => true,
            'status' => 'active',
            ...$validated,
        ]);

        return response()->json(['message' => 'Package created successfully', 'data' => $package], 201);
    }

    public function show(Request $request, $id)
    {
        $package = Package::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        return response()->json(['data' => $package]);
    }

    public function update(Request $request, $id)
    {
        $package = Package::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'days' => 'nullable|integer|min:0',
            'nights' => 'nullable|integer|min:0',
            'destination' => 'nullable|string|max:255',
            'base_price' => 'nullable|numeric|min:0',
            'inclusions' => 'nullable|array',
            'exclusions' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'status' => 'sometimes|string',
        ]);

        $package->update($validated);

        return response()->json(['message' => 'Package updated successfully', 'data' => $package]);
    }

    public function destroy(Request $request, $id)
    {
        $package = Package::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $package->delete();

        return response()->json(['message' => 'Package deleted successfully']);
    }
}
