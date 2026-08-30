<?php
namespace App\Http\Controllers;

use App\Models\SalesStrategy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesStrategyController extends Controller
{
    public function index(Request $request)
    {
        $strategies = SalesStrategy::where('tenant_id', $request->user->tenant_id)
            ->orderBy('priority')
            ->get();

        return response()->json([
            'data' => $strategies,
            'available_keys' => SalesStrategy::KEYS,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => ['required', Rule::in(SalesStrategy::KEYS)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prompt_guidance' => 'required|string',
            'tone' => 'nullable|string|max:64',
            'priority' => 'nullable|integer|min:0',
            'customer_segment_id' => 'nullable|exists:customer_segments,id',
        ]);

        $strategy = SalesStrategy::create([
            'tenant_id' => $request->user->tenant_id,
            'is_active' => true,
            ...$validated,
        ]);

        return response()->json(['data' => $strategy], 201);
    }

    public function update(Request $request, $id)
    {
        $strategy = SalesStrategy::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'prompt_guidance' => 'sometimes|string',
            'tone' => 'nullable|string|max:64',
            'priority' => 'sometimes|integer|min:0',
            'is_active' => 'boolean',
            'customer_segment_id' => 'nullable|exists:customer_segments,id',
        ]);

        $strategy->update($validated);

        return response()->json(['data' => $strategy]);
    }

    public function destroy(Request $request, $id)
    {
        $strategy = SalesStrategy::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $strategy->delete();

        return response()->json(['message' => 'Strategy deleted']);
    }
}
