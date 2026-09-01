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
            'departure_date' => 'required|datetime',
            'arrival_date' => 'required|datetime',
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

    public function bookFlight(Request $request)
    {
        $validated = $request->validate([
            'flight_id' => 'required|exists:flights,id',
            'booking_id' => 'required|exists:bookings,id',
            'travelers' => 'required|array',
        ]);

        $flight = Flight::findOrFail($validated['flight_id']);

        if ($flight->available_seats < count($validated['travelers'])) {
            return response()->json(['error' => 'Not enough seats'], 400);
        }

        foreach ($validated['travelers'] as $index => $traveler_id) {
            FlightBooking::create([
                'booking_id' => $validated['booking_id'],
                'flight_id' => $flight->id,
                'booking_traveler_id' => $traveler_id,
                'seat_number' => chr(65 + floor($index / 6)) . ($index % 6 + 1),
                'status' => 'booked',
            ]);
        }

        $flight->decrement('available_seats', count($validated['travelers']));

        return response()->json(['message' => 'Flight booked successfully'], 201);
    }
}
