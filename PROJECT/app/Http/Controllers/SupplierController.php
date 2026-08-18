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
}
