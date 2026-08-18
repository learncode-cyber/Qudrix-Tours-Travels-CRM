<?php
namespace App\Http\Controllers\API;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantController
{
    public function index()
    {
        return response()->json(['data' => Tenant::paginate(15)]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['name' => 'required', 'email' => 'required|email', 'slug' => 'required|unique:tenants']);
        $tenant = Tenant::create($validated + ['is_active' => true]);
        return response()->json(['data' => $tenant], 201);
    }

    public function show(Tenant $tenant)
    {
        return response()->json(['data' => $tenant]);
    }
}
