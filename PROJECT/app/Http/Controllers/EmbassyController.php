<?php

namespace App\Http\Controllers;

use App\Models\Embassy;
use Illuminate\Http\Request;

class EmbassyController extends Controller
{
    public function index(Request $request)
    {
        $query = Embassy::where('tenant_id', $request->user->tenant_id);

        if ($request->country) {
            $query->where('country', $request->country);
        }

        $embassies = $query->orderBy('name')->paginate($request->per_page ?? 50);

        return response()->json([
            'data' => $embassies->items(),
            'pagination' => [
                'total' => $embassies->total(),
                'per_page' => $embassies->perPage(),
                'current_page' => $embassies->currentPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'required|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string|max:30',
            'website' => 'nullable|string|max:255',
            'average_processing_days' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $embassy = Embassy::create([
            'tenant_id' => $request->user->tenant_id,
            ...$validated,
        ]);

        return response()->json(['message' => 'Embassy created successfully', 'data' => $embassy], 201);
    }

    public function show(Request $request, $id)
    {
        $embassy = Embassy::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        return response()->json(['data' => $embassy]);
    }

    public function update(Request $request, $id)
    {
        $embassy = Embassy::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'country' => 'sometimes|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string|max:30',
            'website' => 'nullable|string|max:255',
            'average_processing_days' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $embassy->update($validated);

        return response()->json(['message' => 'Embassy updated successfully', 'data' => $embassy]);
    }

    public function destroy(Request $request, $id)
    {
        $embassy = Embassy::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $embassy->delete();

        return response()->json(['message' => 'Embassy deleted successfully']);
    }
}
