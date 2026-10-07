<?php
namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $vendors = Vendor::where('tenant_id', $request->user->tenant_id)
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['data' => $vendors->items(), 'pagination' => [
            'total' => $vendors->total(),
            'per_page' => $vendors->perPage(),
            'current_page' => $vendors->currentPage(),
            'last_page' => $vendors->lastPage(),
        ]]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'category' => 'required|in:printing,marketing,software,office_supplies,utilities,other',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'contact_person' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $vendor = Vendor::create(['tenant_id' => $request->user->tenant_id, 'status' => 'active', ...$validated]);

        return response()->json(['data' => $vendor], 201);
    }

    public function show(Request $request, $id)
    {
        $vendor = Vendor::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        return response()->json(['data' => $vendor]);
    }

    public function update(Request $request, $id)
    {
        $vendor = Vendor::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'category' => 'sometimes|in:printing,marketing,software,office_supplies,utilities,other',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'contact_person' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

        $vendor->update($validated);

        return response()->json(['data' => $vendor]);
    }

    public function destroy(Request $request, $id)
    {
        $vendor = Vendor::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $vendor->delete();

        return response()->json(['message' => 'Vendor removed']);
    }
}
