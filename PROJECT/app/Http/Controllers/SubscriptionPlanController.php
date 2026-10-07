<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    /**
     * List all subscription plans for this tenant.
     * Admin only — customers see these via a public endpoint.
     */
    public function index(Request $request)
    {
        $plans = SubscriptionPlan::where('tenant_id', $request->user->tenant_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return response()->json(['data' => $plans]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'currency' => 'required|string|size:3',
            'max_users' => 'nullable|integer|min:1',
            'max_api_calls_per_day' => 'nullable|integer|min:0',
            'max_contacts' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'display_order' => 'nullable|integer',
        ]);

        $plan = SubscriptionPlan::create([
            'tenant_id' => $request->user->tenant_id,
            ...$validated,
        ]);

        return response()->json(['data' => $plan], 201);
    }

    public function update(Request $request, $id)
    {
        $plan = SubscriptionPlan::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'max_users' => 'nullable|integer|min:1',
            'max_api_calls_per_day' => 'nullable|integer|min:0',
            'max_contacts' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'display_order' => 'nullable|integer',
        ]);

        $plan->update($validated);

        return response()->json(['data' => $plan]);
    }

    public function delete(Request $request, $id)
    {
        $plan = SubscriptionPlan::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $plan->delete();

        return response()->json(['message' => 'Plan deleted']);
    }
}
