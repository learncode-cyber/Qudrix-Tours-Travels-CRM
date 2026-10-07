<?php
namespace App\Http\Controllers;

use App\Models\BookingTraveler;
use App\Models\MahramRelationship;
use Illuminate\Http\Request;

class MahramController extends Controller
{
    /**
     * List mahram records for every traveler on a booking (tenant-scoped
     * via the booking itself, since booking_travelers/mahram_relationships
     * don't carry a direct customer-facing identifier of their own).
     */
    public function indexForBooking(Request $request, $bookingId)
    {
        $records = MahramRelationship::where('tenant_id', $request->user->tenant_id)
            ->whereHas('traveler', fn ($q) => $q->where('booking_id', $bookingId))
            ->with(['traveler', 'mahramTraveler'])
            ->get();

        return response()->json(['data' => $records]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_traveler_id' => 'required|exists:booking_travelers,id',
            'mahram_traveler_id' => 'nullable|exists:booking_travelers,id',
            'mahram_name' => 'required_without:mahram_traveler_id|nullable|string',
            'mahram_phone' => 'nullable|string',
            'mahram_passport_number' => 'nullable|string',
            'relationship_type' => 'required|in:husband,father,son,brother,grandfather,uncle,other',
            'notes' => 'nullable|string',
        ]);

        // Tenant isolation: the traveler being referenced must actually
        // belong to this tenant (via its booking), otherwise a user from
        // one tenant could attach a mahram record to another tenant's
        // traveler by guessing an ID.
        $traveler = BookingTraveler::whereHas('booking', fn ($q) => $q->where('tenant_id', $request->user->tenant_id))
            ->findOrFail($validated['booking_traveler_id']);

        $record = MahramRelationship::create([
            'tenant_id' => $request->user->tenant_id,
            'is_verified' => false,
            ...$validated,
        ]);

        return response()->json(['data' => $record], 201);
    }

    public function verify(Request $request, $id)
    {
        $record = MahramRelationship::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $record->update([
            'is_verified' => true,
            'verified_by' => $request->user->id,
            'verified_at' => now(),
        ]);

        return response()->json(['data' => $record]);
    }

    public function destroy(Request $request, $id)
    {
        $record = MahramRelationship::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $record->delete();

        return response()->json(['message' => 'Mahram record removed']);
    }
}
