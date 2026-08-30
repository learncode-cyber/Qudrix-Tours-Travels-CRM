<?php
namespace App\Http\Controllers;

use App\Models\CustomerMemory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Customer memory is permission-controlled, editable, deletable and
// auditable (Directive S9). Writes pass through the 'audit' middleware
// like every other protected route, so who changed what is recorded.
class CustomerMemoryController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'lead_id' => 'nullable|exists:leads,id',
        ]);

        if (empty($validated['customer_id']) && empty($validated['lead_id'])) {
            return response()->json(['error' => 'Provide customer_id or lead_id.'], 422);
        }

        $memories = CustomerMemory::where('tenant_id', $request->user->tenant_id)
            ->when($validated['customer_id'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($validated['lead_id'] ?? null, fn ($q, $v) => $q->where('lead_id', $v))
            ->with('createdBy:id,name')
            ->latest()
            ->get();

        return response()->json([
            'data' => $memories,
            'categories' => CustomerMemory::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'lead_id' => 'nullable|exists:leads,id',
            'category' => ['required', Rule::in(CustomerMemory::CATEGORIES)],
            'key' => 'required|string|max:255',
            'value' => 'required|string|max:2000',
            'source' => ['nullable', Rule::in(['human', 'ai_extracted'])],
            'confidence' => 'nullable|numeric|between:0,1',
            'is_sensitive' => 'boolean',
        ]);

        if (empty($validated['customer_id']) && empty($validated['lead_id'])) {
            return response()->json(['error' => 'A memory entry must be linked to a customer or a lead.'], 422);
        }

        $memory = CustomerMemory::create([
            'tenant_id' => $request->user->tenant_id,
            'created_by' => $request->user->id,
            'source' => $validated['source'] ?? 'human',
            ...$validated,
        ]);

        return response()->json(['data' => $memory], 201);
    }

    public function update(Request $request, $id)
    {
        $memory = CustomerMemory::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'value' => 'sometimes|string|max:2000',
            'category' => ['sometimes', Rule::in(CustomerMemory::CATEGORIES)],
            'is_sensitive' => 'boolean',
        ]);

        $memory->update($validated);

        return response()->json(['data' => $memory]);
    }

    public function destroy(Request $request, $id)
    {
        $memory = CustomerMemory::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $memory->delete();

        return response()->json(['message' => 'Memory entry deleted']);
    }
}
