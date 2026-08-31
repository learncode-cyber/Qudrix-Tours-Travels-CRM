<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function index(Request $request)
    {
        $query = Destination::where('tenant_id', $request->user->tenant_id);

        if ($request->country) {
            $query->where('country', $request->country);
        }

        if ($request->city) {
            $query->where('city', $request->city);
        }

        $destinations = $query->paginate($request->per_page ?? 20);

        return response()->json(['data' => $destinations->items()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'country' => 'required|string',
            'city' => 'required|string',
            'region' => 'nullable|string',
            'description' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'tourist_season' => 'nullable|string',
            'weather_info' => 'nullable|string',
            'visa_required' => 'nullable|boolean',
            'currency' => 'required|string|size:3',
            'language' => 'nullable|string',
        ]);

        $destination = Destination::create([
            'tenant_id' => $request->user->tenant_id,
            ...$validated
        ]);

        return response()->json(['data' => $destination], 201);
    }

    public function show(Request $request, $id)
    {
        $destination = Destination::where('tenant_id', $request->user->tenant_id)
            ->findOrFail($id);

        return response()->json(['data' => $destination]);
    }

    public function update(Request $request, $id)
    {
        $destination = Destination::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'description' => 'nullable|string',
            'weather_info' => 'nullable|string',
            'tourist_season' => 'nullable|string',
        ]);

        $destination->update($validated);

        return response()->json(['data' => $destination]);
    }

    // apiResource('destinations', ...) registers DELETE
    // /destinations/{destination} — it didn't exist, so that route
    // 500'd the moment anything called it.
    public function destroy(Request $request, $id)
    {
        $destination = Destination::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $destination->delete();

        return response()->json(['message' => 'Destination deleted successfully']);
    }
}
