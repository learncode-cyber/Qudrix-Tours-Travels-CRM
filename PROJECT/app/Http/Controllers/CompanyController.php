<?php
namespace App\Http\Controllers;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::where('tenant_id', $request->user->tenant_id)
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->paginate(20);
        return response()->json(['data' => $companies->items()]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'industry' => 'nullable|string',
            'website' => 'nullable|url',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        $company = Company::create(['tenant_id' => $request->user->tenant_id, ...$validated]);
        return response()->json(['data' => $company], 201);
    }
    public function show(Request $request, $id)
    {
        $company = Company::where('tenant_id', $request->user->tenant_id)->with('contacts')->findOrFail($id);
        return response()->json(['data' => $company]);
    }
    public function update(Request $request, $id)
    {
        $company = Company::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'industry' => 'nullable|string',
            'website' => 'nullable|url',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        $company->update($validated);
        return response()->json(['data' => $company]);
    }
    public function destroy(Request $request, $id)
    {
        $company = Company::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $company->delete();
        return response()->json(['message' => 'Company deleted']);
    }
}
