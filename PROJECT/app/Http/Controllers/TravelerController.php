<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingTraveler;
use Illuminate\Http\Request;

class TravelerController extends Controller
{
    public function addTraveler(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email',
            'phone' => 'required|string',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'passport_number' => 'required|string',
            'passport_expiry' => 'required|date|after:today',
            'national_id' => 'nullable|string',
            'nationality' => 'required|string|size:2',
            'traveler_type' => 'required|in:adult,child,infant',
            'is_primary_contact' => 'nullable|boolean',
            'emergency_contact' => 'required|string',
            'emergency_phone' => 'required|string',
            'room_preference' => 'nullable|string',
        ]);

        $booking = Booking::where('tenant_id', $request->user->tenant_id)->findOrFail($validated['booking_id']);

        $traveler = BookingTraveler::create($validated);

        return response()->json([
            'message' => 'Traveler added successfully',
            'data' => $traveler
        ], 201);
    }

    public function getTravelers(Request $request, $bookingId)
    {
        $travelers = BookingTraveler::whereHas('booking', fn ($q) => $q->where('tenant_id', $request->user->tenant_id))
            ->where('booking_id', $bookingId)
            ->orderBy('is_primary_contact', 'desc')
            ->get();

        return response()->json(['data' => $travelers]);
    }

    public function updateTraveler(Request $request, $id)
    {
        $traveler = BookingTraveler::whereHas('booking', fn ($q) => $q->where('tenant_id', $request->user->tenant_id))->findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email',
            'phone' => 'sometimes|string',
            'passport_number' => 'sometimes|string',
            'passport_expiry' => 'sometimes|date|after:today',
            'room_preference' => 'nullable|string',
        ]);

        $traveler->update($validated);

        return response()->json([
            'message' => 'Traveler updated',
            'data' => $traveler
        ]);
    }

    public function removeTraveler(Request $request, $id)
    {
        $traveler = BookingTraveler::whereHas('booking', fn ($q) => $q->where('tenant_id', $request->user->tenant_id))->findOrFail($id);
        $traveler->delete();

        return response()->json(['message' => 'Traveler removed']);
    }

    public function getTravelerDetails(Request $request, $id)
    {
        $traveler = BookingTraveler::whereHas('booking', fn ($q) => $q->where('tenant_id', $request->user->tenant_id))->findOrFail($id);

        $details = [
            'full_name' => $traveler->getFullName(),
            'age' => $traveler->getAge(),
            'passport_valid' => $traveler->isPassportValid(),
            'traveler' => $traveler
        ];

        return response()->json(['data' => $details]);
    }
}
