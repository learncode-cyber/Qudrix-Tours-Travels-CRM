<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealStageTransition;
use Illuminate\Http\Request;

class DealController extends Controller
{
    private const STAGES = ['new', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];

    public function index(Request $request)
    {
        $query = Deal::where('tenant_id', $request->user->tenant_id)
            ->with(['customer', 'lead', 'owner']);

        if ($request->stage) {
            $query->where('stage', $request->stage);
        }

        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->owner_id) {
            $query->where('owner_id', $request->owner_id);
        }

        $deals = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $deals->items(),
            'pagination' => [
                'total' => $deals->total(),
                'per_page' => $deals->perPage(),
                'current_page' => $deals->currentPage(),
                'last_page' => $deals->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'customer_id' => 'nullable|exists:customers,id',
            'lead_id' => 'nullable|exists:leads,id',
            'owner_id' => 'nullable|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'probability' => 'nullable|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $deal = Deal::create([
            'tenant_id' => $request->user->tenant_id,
            'owner_id' => $validated['owner_id'] ?? $request->user->id,
            'stage' => 'new',
            'probability' => $validated['probability'] ?? 10,
            ...$validated,
        ]);

        DealStageTransition::create([
            'tenant_id' => $deal->tenant_id,
            'deal_id' => $deal->id,
            'stage' => 'new',
            'entered_at' => now(),
        ]);

        return response()->json([
            'message' => 'Deal created successfully',
            'data' => $deal,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $deal = Deal::where('tenant_id', $request->user->tenant_id)
            ->with(['customer', 'lead', 'owner'])
            ->findOrFail($id);

        $transitions = DealStageTransition::where('deal_id', $id)
            ->orderBy('entered_at', 'desc')
            ->get();

        return response()->json([
            'data' => $deal,
            'stage_history' => $transitions,
        ]);
    }

    public function update(Request $request, $id)
    {
        $deal = Deal::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0',
            'owner_id' => 'nullable|exists:users,id',
            'probability' => 'sometimes|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if (array_key_exists('stage', $request->all())) {
            return response()->json([
                'message' => 'Use PUT /deals/{id}/stage to change stage (keeps stage history accurate).',
            ], 422);
        }

        $deal->update($validated);

        return response()->json([
            'message' => 'Deal updated successfully',
            'data' => $deal,
        ]);
    }

    public function updateStage(Request $request, $id)
    {
        $deal = Deal::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'stage' => 'required|in:' . implode(',', self::STAGES),
        ]);

        $currentTransition = DealStageTransition::where('deal_id', $deal->id)
            ->whereNull('exited_at')
            ->first();

        if ($currentTransition) {
            $currentTransition->update([
                'exited_at' => now(),
                'duration_days' => $currentTransition->calculateDuration(),
            ]);
        }

        DealStageTransition::create([
            'tenant_id' => $deal->tenant_id,
            'deal_id' => $deal->id,
            'stage' => $validated['stage'],
            'entered_at' => now(),
        ]);

        $deal->update([
            'stage' => $validated['stage'],
            'closed_at' => in_array($validated['stage'], ['won', 'lost'], true) ? now() : null,
        ]);

        return response()->json([
            'message' => 'Deal stage updated',
            'data' => $deal,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $deal = Deal::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $deal->delete();

        return response()->json(['message' => 'Deal deleted successfully']);
    }

    public function pipeline(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $stages = [];
        foreach (self::STAGES as $stage) {
            $deals = Deal::where('tenant_id', $tenantId)
                ->where('stage', $stage)
                ->with(['customer', 'owner'])
                ->get();

            $stages[$stage] = [
                'count' => $deals->count(),
                'total_value' => $deals->sum('amount'),
                'deals' => $deals,
            ];
        }

        return response()->json([
            'data' => $stages,
            'pipeline_value' => array_reduce($stages, fn ($carry, $s) => $carry + $s['total_value'], 0),
        ]);
    }
}
