<?php
namespace App\Http\Controllers;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::where('tenant_id', $request->user->tenant_id)->paginate(20);
        return response()->json(['data' => $suppliers->items()]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:airline,hotel,transport,visa,guide',
            'email' => 'required|email',
            'phone' => 'required|string',
            'commission_rate' => 'required|numeric|min:0|max:100',
        ]);
        $supplier = Supplier::create(['tenant_id' => $request->user->tenant_id, 'status' => 'active', ...$validated]);
        return response()->json(['data' => $supplier], 201);
    }
    public function show(Request $request, $id)
    {
        $supplier = Supplier::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        return response()->json(['data' => $supplier]);
    }

    // FIX (MASTER_PROJECT_AUDIT.md): update/destroy were missing even
    // though the route file registered a full apiResource for suppliers,
    // meaning PUT/PATCH/DELETE on /suppliers/{id} would 500 at runtime.
    public function update(Request $request, $id)
    {
        $supplier = Supplier::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'type' => 'sometimes|in:airline,hotel,transport,visa,guide,other',
            'email' => 'sometimes|email',
            'phone' => 'sometimes|string',
            'contact_person' => 'nullable|string',
            'commission_rate' => 'sometimes|numeric|min:0|max:100',
            'contract_terms' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,suspended',
        ]);
        $supplier->update($validated);
        return response()->json(['data' => $supplier]);
    }

    public function destroy(Request $request, $id)
    {
        $supplier = Supplier::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $supplier->delete();
        return response()->json(['message' => 'Supplier removed']);
    }
}
