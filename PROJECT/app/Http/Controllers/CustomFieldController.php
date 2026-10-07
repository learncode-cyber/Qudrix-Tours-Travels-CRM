<?php

namespace App\Http\Controllers;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use Illuminate\Http\Request;

class CustomFieldController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomField::where('tenant_id', $request->user->tenant_id);

        if ($request->entity_type) {
            $query->where('entity_type', $request->entity_type);
        }

        $fields = $query->orderBy('sort_order')->get();

        return response()->json(['data' => $fields]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|string|max:100',
            'name' => 'required|string|max:100|regex:/^[a-z0-9_]+$/',
            'label' => 'required|string|max:255',
            'field_type' => 'required|in:text,number,date,select,multi_select,boolean',
            'options' => 'nullable|array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $field = CustomField::create([
            'tenant_id' => $request->user->tenant_id,
            ...$validated,
        ]);

        return response()->json(['data' => $field], 201);
    }

    public function update(Request $request, $id)
    {
        $field = CustomField::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'label' => 'sometimes|string|max:255',
            'options' => 'nullable|array',
            'is_required' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $field->update($validated);

        return response()->json(['data' => $field]);
    }

    public function delete(Request $request, $id)
    {
        $field = CustomField::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $field->delete();

        return response()->json(['message' => 'Custom field deleted successfully']);
    }

    /**
     * Set values for one entity instance in a single call, e.g.
     * { "entity_type": "customer", "entity_id": 5, "values": { "loyalty_tier": "gold" } }
     */
    public function setValues(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|string',
            'entity_id' => 'required|integer',
            'values' => 'required|array',
        ]);

        $tenantId = $request->user->tenant_id;

        $fields = CustomField::where('tenant_id', $tenantId)
            ->where('entity_type', $validated['entity_type'])
            ->whereIn('name', array_keys($validated['values']))
            ->get()
            ->keyBy('name');

        $missingRequired = CustomField::where('tenant_id', $tenantId)
            ->where('entity_type', $validated['entity_type'])
            ->where('is_required', true)
            ->pluck('name')
            ->diff(array_keys($validated['values']));

        if ($missingRequired->isNotEmpty()) {
            return response()->json([
                'message' => 'Missing required custom fields',
                'missing' => $missingRequired->values(),
            ], 422);
        }

        $saved = [];
        foreach ($validated['values'] as $name => $value) {
            if (!isset($fields[$name])) {
                continue; // unknown field name for this entity_type, skip silently
            }

            $saved[] = CustomFieldValue::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'custom_field_id' => $fields[$name]->id,
                    'entity_type' => $validated['entity_type'],
                    'entity_id' => $validated['entity_id'],
                ],
                ['value' => is_array($value) ? json_encode($value) : $value]
            );
        }

        return response()->json(['data' => $saved]);
    }

    public function getValues(Request $request, string $entityType, int $entityId)
    {
        $values = CustomFieldValue::where('tenant_id', $request->user->tenant_id)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->with('field')
            ->get()
            ->mapWithKeys(fn ($v) => [$v->field->name => $v->value]);

        return response()->json(['data' => $values]);
    }
}
