<?php
namespace App\Http\Controllers;

use App\Models\BookingTraveler;
use App\Models\HotelBooking;
use App\Models\RoomAssignment;
use Illuminate\Http\Request;

class RoomAssignmentController extends Controller
{
    public function indexForHotelBooking(Request $request, $hotelBookingId)
    {
        $rooms = RoomAssignment::where('tenant_id', $request->user->tenant_id)
            ->where('hotel_booking_id', $hotelBookingId)
            ->with('travelers')
            ->get();

        return response()->json(['data' => $rooms]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'hotel_booking_id' => 'required|exists:hotel_bookings,id',
            'room_number' => 'nullable|string',
            'room_type' => 'required|string',
            'max_occupancy' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        // Tenant isolation via the parent booking on hotel_bookings.
        $hotelBooking = HotelBooking::whereHas('booking', fn ($q) => $q->where('tenant_id', $request->user->tenant_id))
            ->findOrFail($validated['hotel_booking_id']);

        $room = RoomAssignment::create(['tenant_id' => $request->user->tenant_id, ...$validated]);

        return response()->json(['data' => $room], 201);
    }

    /**
     * Sync which travelers occupy a room. Rejects the sync (rather than
     * silently overcrowding) if it would exceed max_occupancy — a real
     * check, not just a stored number nobody enforces.
     */
    public function assignTravelers(Request $request, $id)
    {
        $room = RoomAssignment::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'booking_traveler_ids' => 'required|array',
            'booking_traveler_ids.*' => 'exists:booking_travelers,id',
        ]);

        if (count($validated['booking_traveler_ids']) > $room->max_occupancy) {
            return response()->json([
                'error' => "Cannot assign " . count($validated['booking_traveler_ids']) . " travelers to a room with max_occupancy {$room->max_occupancy}",
            ], 422);
        }

        // Every traveler ID must belong to this tenant (via the room's own
        // booking chain), otherwise cross-tenant traveler IDs could be synced in.
        $validCount = BookingTraveler::whereIn('id', $validated['booking_traveler_ids'])
            ->whereHas('booking', fn ($q) => $q->where('tenant_id', $request->user->tenant_id))
            ->count();

        if ($validCount !== count($validated['booking_traveler_ids'])) {
            return response()->json(['error' => 'One or more travelers do not belong to this tenant'], 422);
        }

        $room->travelers()->sync($validated['booking_traveler_ids']);

        return response()->json(['data' => $room->load('travelers')]);
    }

    public function destroy(Request $request, $id)
    {
        $room = RoomAssignment::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $room->delete();

        return response()->json(['message' => 'Room assignment removed']);
    }
}
