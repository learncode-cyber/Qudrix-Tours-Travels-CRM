<?php

namespace App\Http\Controllers;

use App\Models\PricingRule;
use Illuminate\Http\Request;

class PricingRuleController extends Controller
{
    public function index(Request $request)
    {
        $query = PricingRule::where('tenant_id', $request->user->tenant_id);

        if ($request->rule_type) {
            $query->where('rule_type', $request->rule_type);
        }

        return response()->json(['data' => $query->orderBy('priority')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rule_type' => 'required|in:season,demand,group_size,booking_timing,customer_segment',
            'conditions' => 'required|array',
            'adjustment_type' => 'required|in:percentage,fixed',
            'adjustment_value' => 'required|numeric',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ]);

        $rule = PricingRule::create([
            'tenant_id' => $request->user->tenant_id,
            ...$validated,
        ]);

        return response()->json(['data' => $rule], 201);
    }

    public function update(Request $request, $id)
    {
        $rule = PricingRule::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'conditions' => 'sometimes|array',
            'adjustment_type' => 'sometimes|in:percentage,fixed',
            'adjustment_value' => 'sometimes|numeric',
            'priority' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        $rule->update($validated);

        return response()->json(['data' => $rule]);
    }

    public function delete(Request $request, $id)
    {
        $rule = PricingRule::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $rule->delete();

        return response()->json(['message' => 'Pricing rule deleted']);
    }
}
