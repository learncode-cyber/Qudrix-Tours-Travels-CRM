<?php
namespace App\Http\Controllers;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $vendors = Vendor::where('tenant_id', $request->user->tenant_id)->paginate(20);
        return response()->json(['data' => $vendors->items()]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'category' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'contact_person' => 'nullable|string',
            'address' => 'nullable|string',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
            'contract_terms' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'payment_terms' => 'nullable|string',
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
            'category' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,suspended',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);
        $vendor->update($validated);
        return response()->json(['data' => $vendor]);
    }
}
