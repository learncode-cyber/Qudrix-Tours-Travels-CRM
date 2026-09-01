<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingItinerary;
use Illuminate\Http\Request;

class ItineraryController extends Controller
{
    public function createItinerary(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'day_number' => 'required|integer|min:1',
            'date' => 'required|date',
            'location' => 'required|string|max:255',
            'activity_type' => 'required|in:sightseeing,hotel,flight,transport,meal,worship,free_time',
            'activity_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s',
            'hotel_name' => 'nullable|string',
            'meal_type' => 'nullable|in:breakfast,lunch,dinner,all',
            'transportation_type' => 'nullable|in:bus,flight,train,car',
            'notes' => 'nullable|string',
        ]);

        $itinerary = BookingItinerary::create($validated);

        return response()->json([
            'message' => 'Itinerary created',
            'data' => $itinerary
        ], 201);
    }

    public function getItinerary(Request $request, $bookingId)
    {
        $itinerary = BookingItinerary::where('booking_id', $bookingId)
            ->orderBy('day_number')
            ->orderBy('start_time')
            ->get();

        return response()->json(['data' => $itinerary]);
    }

    public function updateItinerary(Request $request, $id)
    {
        $itinerary = BookingItinerary::findOrFail($id);

        $validated = $request->validate([
            'activity_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s',
            'notes' => 'nullable|string',
        ]);

        $itinerary->update($validated);

        return response()->json([
            'message' => 'Itinerary updated',
            'data' => $itinerary
        ]);
    }

    public function deleteItinerary(Request $request, $id)
    {
        $itinerary = BookingItinerary::findOrFail($id);
        $itinerary->delete();

        return response()->json(['message' => 'Itinerary deleted']);
    }

    public function generateItineraryPdf(Request $request, $bookingId)
    {
        $booking = Booking::with(['itinerary', 'travelers', 'customer', 'package'])
            ->findOrFail($bookingId);

        $itinerary = $booking->itinerary()->orderBy('day_number')->get();

        $pdfData = [
            'booking_number' => $booking->booking_number,
            'customer_name' => $booking->customer->name,
            'travel_date' => $booking->travel_date->format('Y-m-d'),
            'return_date' => $booking->return_date->format('Y-m-d'),
            'package' => $booking->package->name,
            'travelers' => $booking->travelers()->count(),
            'itinerary' => $itinerary->toArray(),
        ];

        return response()->json([
            'message' => 'Itinerary PDF generated',
            'data' => $pdfData
        ]);
    }
}
