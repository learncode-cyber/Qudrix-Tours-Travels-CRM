<?php
namespace App\Http\Controllers;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use Illuminate\Http\Request;

class CustomFieldController extends Controller
{
    public function index(Request $request)
    {
        $fields = CustomFieldDefinition::where('tenant_id', $request->user->tenant_id)
            ->when($request->entity_type, fn ($q) => $q->where('entity_type', $request->entity_type))
            ->get();
        return response()->json(['data' => $fields]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|string',
            'key' => 'required|string|alpha_dash',
            'label' => 'required|string',
            'field_type' => 'required|in:text,number,date,boolean,select',
            'options' => 'nullable|array',
            'is_required' => 'boolean',
        ]);
        $field = CustomFieldDefinition::create(['tenant_id' => $request->user->tenant_id, ...$validated]);
        return response()->json(['data' => $field], 201);
    }
    public function destroy(Request $request, $id)
    {
        $field = CustomFieldDefinition::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $field->delete();
        return response()->json(['message' => 'Custom field definition deleted']);
    }
    public function setValue(Request $request)
    {
        $validated = $request->validate([
            'custom_field_definition_id' => 'required|exists:custom_field_definitions,id',
            'entity_type' => 'required|string',
            'entity_id' => 'required|integer',
            'value' => 'nullable|string',
        ]);
        $definition = CustomFieldDefinition::where('tenant_id', $request->user->tenant_id)
            ->findOrFail($validated['custom_field_definition_id']);

        $value = CustomFieldValue::updateOrCreate(
            [
                'custom_field_definition_id' => $definition->id,
                'entity_type' => $validated['entity_type'],
                'entity_id' => $validated['entity_id'],
            ],
            [
                'tenant_id' => $request->user->tenant_id,
                'value' => $validated['value'] ?? null,
            ]
        );
        return response()->json(['data' => $value]);
    }
    public function valuesFor(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|string',
            'entity_id' => 'required|integer',
        ]);
        $values = CustomFieldValue::where('tenant_id', $request->user->tenant_id)
            ->where('entity_type', $validated['entity_type'])
            ->where('entity_id', $validated['entity_id'])
            ->with('definition')
            ->get();
        return response()->json(['data' => $values]);
    }
}
