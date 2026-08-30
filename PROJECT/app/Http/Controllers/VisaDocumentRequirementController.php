<?php
namespace App\Http\Controllers;
use App\Models\VisaDocumentRequirement;
use Illuminate\Http\Request;

class VisaDocumentRequirementController extends Controller
{
    public function index(Request $request)
    {
        $requirements = VisaDocumentRequirement::where('tenant_id', $request->user->tenant_id)
            ->when($request->destination_country, fn ($q) => $q->where('destination_country', $request->destination_country))
            ->when($request->visa_type, fn ($q) => $q->where('visa_type', $request->visa_type))
            ->get();
        return response()->json(['data' => $requirements]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'destination_country' => 'required|string|size:2',
            'visa_type' => 'required|string',
            'document_name' => 'required|string',
            'is_mandatory' => 'boolean',
        ]);
        $requirement = VisaDocumentRequirement::create(['tenant_id' => $request->user->tenant_id, ...$validated]);
        return response()->json(['data' => $requirement], 201);
    }
    public function destroy(Request $request, $id)
    {
        $requirement = VisaDocumentRequirement::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $requirement->delete();
        return response()->json(['message' => 'Requirement deleted']);
    }
}
