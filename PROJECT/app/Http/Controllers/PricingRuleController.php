<?php
namespace App\Http\Controllers;
use App\Models\PricingRule;
use App\Services\PricingEngine;
use Illuminate\Http\Request;

class PricingRuleController extends Controller
{
    public function index(Request $request)
    {
        $rules = PricingRule::where('tenant_id', $request->user->tenant_id)
            ->orderBy('priority')
            ->get();
        return response()->json(['data' => $rules]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'factor' => 'required|in:season,demand,group_size,customer_segment,booking_timing',
            'season_start' => 'nullable|date',
            'season_end' => 'nullable|date|after_or_equal:season_start',
            'min_group_size' => 'nullable|integer|min:1',
            'max_group_size' => 'nullable|integer|gte:min_group_size',
            'booking_days_before_travel_min' => 'nullable|integer|min:0',
            'booking_days_before_travel_max' => 'nullable|integer|gte:booking_days_before_travel_min',
            'customer_segment_id' => 'nullable|exists:customer_segments,id',
            'adjustment_type' => 'required|in:percentage,fixed',
            'adjustment_value' => 'required|numeric',
            'priority' => 'nullable|integer|min:0',
        ]);
        $rule = PricingRule::create(['tenant_id' => $request->user->tenant_id, 'is_active' => true, ...$validated]);
        return response()->json(['data' => $rule], 201);
    }

    public function update(Request $request, $id)
    {
        $rule = PricingRule::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'adjustment_type' => 'sometimes|in:percentage,fixed',
            'adjustment_value' => 'sometimes|numeric',
            'priority' => 'sometimes|integer|min:0',
            'is_active' => 'boolean',
        ]);
        $rule->update($validated);
        return response()->json(['data' => $rule]);
    }

    public function destroy(Request $request, $id)
    {
        $rule = PricingRule::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $rule->delete();
        return response()->json(['message' => 'Pricing rule deleted']);
    }

    // Preview a calculation without attaching it to any booking/quotation —
    // still logged, since every calculation must be auditable.
    public function preview(Request $request, PricingEngine $engine)
    {
        $validated = $request->validate([
            'base_cost' => 'required|numeric|min:0',
            'travel_date' => 'nullable|date',
            'group_size' => 'nullable|integer|min:1',
            'booking_days_before_travel' => 'nullable|integer|min:0',
            'customer_segment_id' => 'nullable|exists:customer_segments,id',
        ]);
        $baseCost = $validated['base_cost'];
        unset($validated['base_cost']);

        $result = $engine->calculate($request->user->tenant_id, $baseCost, $validated, $request->user->id);
        return response()->json(['data' => $result]);
    }
}
