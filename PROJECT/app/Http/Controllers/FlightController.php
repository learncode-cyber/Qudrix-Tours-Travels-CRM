<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\FlightBooking;
use Illuminate\Http\Request;

class FlightController extends Controller
{
    public function index(Request $request)
    {
        $query = Flight::where('tenant_id', $request->user->tenant_id);

        if ($request->departure_airport) {
            $query->where('departure_airport', $request->departure_airport);
        }

        if ($request->arrival_airport) {
            $query->where('arrival_airport', $request->arrival_airport);
        }

        if ($request->departure_date) {
            $query->whereDate('departure_date', $request->departure_date);
        }

        $flights = $query->paginate($request->per_page ?? 20);

        return response()->json(['data' => $flights->items()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'airline_code' => 'required|string',
            'flight_number' => 'required|string|unique:flights,flight_number',
            'departure_airport' => 'required|string|size:3',
            'arrival_airport' => 'required|string|size:3',
            'departure_date' => 'required|date',
            'arrival_date' => 'required|date',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'departure_time' => 'required|date_format:H:i:s',
            'arrival_time' => 'required|date_format:H:i:s',
            'aircraft_type' => 'required|string',
            'total_seats' => 'required|integer|min:1',
            'price_per_seat' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
        ]);

        $flight = Flight::create([
            'tenant_id' => $request->user->tenant_id,
            'available_seats' => $validated['total_seats'],
            'status' => 'active',
            ...$validated
        ]);

        return response()->json(['data' => $flight], 201);
    }

    public function show(Request $request, $id)
    {
        $flight = Flight::where('tenant_id', $request->user->tenant_id)
            ->with('flightBookings.traveler')
            ->findOrFail($id);

        return response()->json(['data' => $flight]);
    }

    public function update(Request $request, $id)
    {
        $flight = Flight::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'price_per_seat' => 'sometimes|numeric|min:0',
            'available_seats' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,cancelled',
        ]);

        $flight->update($validated);

        return response()->json(['data' => $flight]);
    }

    // apiResource('flights', ...) registers DELETE /flights/{flight} —
    // it didn't exist, so that route 500'd the moment anything called it.
    public function destroy(Request $request, $id)
    {
        $flight = Flight::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $flight->delete();

        return response()->json(['message' => 'Flight deleted successfully']);
    }

    public function bookFlight(Request $request)
    {
        $validated = $request->validate([
            'flight_id' => 'required|exists:flights,id',
            'booking_id' => 'required|exists:bookings,id',
            'travelers' => 'required|array',
            'cabin_class' => 'nullable|in:economy,premium_economy,business,first',
            'baggage_allowance' => 'nullable|string',
            'fare_type' => 'nullable|string',
        ]);

        $flight = Flight::where('tenant_id', $request->user->tenant_id)->findOrFail($validated['flight_id']);

        if ($flight->available_seats < count($validated['travelers'])) {
            return response()->json(['error' => 'Not enough seats'], 400);
        }

        $pnr = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        foreach ($validated['travelers'] as $index => $traveler_id) {
            FlightBooking::create([
                'booking_id' => $validated['booking_id'],
                'flight_id' => $flight->id,
                'booking_traveler_id' => $traveler_id,
                'seat_number' => chr(65 + floor($index / 6)) . ($index % 6 + 1),
                'pnr' => $pnr,
                'cabin_class' => $validated['cabin_class'] ?? 'economy',
                'baggage_allowance' => $validated['baggage_allowance'] ?? null,
                'fare_type' => $validated['fare_type'] ?? null,
                'price_paid' => $flight->price_per_seat,
                'status' => 'booked',
            ]);
        }

        $flight->decrement('available_seats', count($validated['travelers']));

        return response()->json(['message' => 'Flight booked successfully', 'data' => ['pnr' => $pnr]], 201);
    }

    public function cancelFlightBooking(Request $request, $id)
    {
        $flightBooking = FlightBooking::whereHas('flight', fn ($q) => $q->where('tenant_id', $request->user->tenant_id))
            ->findOrFail($id);

        if ($flightBooking->status === 'cancelled') {
            return response()->json(['error' => 'Already cancelled'], 400);
        }

        $validated = $request->validate(['refund_amount' => 'nullable|numeric|min:0']);

        $flightBooking->update([
            'status' => 'cancelled',
            'refund_status' => isset($validated['refund_amount']) ? 'refunded' : 'not_applicable',
            'refund_amount' => $validated['refund_amount'] ?? null,
            'cancelled_at' => now(),
        ]);
        $flightBooking->flight->increment('available_seats');

        return response()->json(['data' => $flightBooking]);
    }
}
