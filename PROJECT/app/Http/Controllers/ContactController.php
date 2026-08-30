<?php
namespace App\Http\Controllers;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $contacts = Contact::where('tenant_id', $request->user->tenant_id)
            ->when($request->company_id, fn ($q) => $q->where('company_id', $request->company_id))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->paginate(20);
        return response()->json(['data' => $contacts->items()]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'customer_id' => 'nullable|exists:customers,id',
            'name' => 'required|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'designation' => 'nullable|string',
            'is_primary' => 'boolean',
            'notes' => 'nullable|string',
        ]);
        $contact = Contact::create(['tenant_id' => $request->user->tenant_id, ...$validated]);
        return response()->json(['data' => $contact], 201);
    }
    public function show(Request $request, $id)
    {
        $contact = Contact::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        return response()->json(['data' => $contact]);
    }
    public function update(Request $request, $id)
    {
        $contact = Contact::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'designation' => 'nullable|string',
            'is_primary' => 'boolean',
            'notes' => 'nullable|string',
        ]);
        $contact->update($validated);
        return response()->json(['data' => $contact]);
    }
    public function destroy(Request $request, $id)
    {
        $contact = Contact::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $contact->delete();
        return response()->json(['message' => 'Contact deleted']);
    }
}
