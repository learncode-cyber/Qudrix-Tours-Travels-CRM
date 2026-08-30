<?php
namespace App\Http\Controllers;
use App\Models\QuotationTemplate;
use Illuminate\Http\Request;

class QuotationTemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = QuotationTemplate::where('tenant_id', $request->user->tenant_id)
            ->where('is_active', true)
            ->get();
        return response()->json(['data' => $templates]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'subject' => 'nullable|string',
            'description' => 'nullable|string',
            'default_items' => 'nullable|array',
            'default_payment_terms' => 'nullable|array',
            'default_validity_days' => 'nullable|integer|min:1',
        ]);
        $template = QuotationTemplate::create(['tenant_id' => $request->user->tenant_id, 'is_active' => true, ...$validated]);
        return response()->json(['data' => $template], 201);
    }
    public function show(Request $request, $id)
    {
        $template = QuotationTemplate::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        return response()->json(['data' => $template]);
    }
    public function update(Request $request, $id)
    {
        $template = QuotationTemplate::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'subject' => 'nullable|string',
            'description' => 'nullable|string',
            'default_items' => 'nullable|array',
            'default_payment_terms' => 'nullable|array',
            'default_validity_days' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);
        $template->update($validated);
        return response()->json(['data' => $template]);
    }
    public function destroy(Request $request, $id)
    {
        $template = QuotationTemplate::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $template->delete();
        return response()->json(['message' => 'Template deleted']);
    }
}
