<?php

namespace App\Http\Controllers;

use App\Models\Transport;
use App\Models\TransportBooking;
use Illuminate\Http\Request;

class TransportController extends Controller
{
    public function index(Request $request)
    {
        $query = Transport::where('tenant_id', $request->user->tenant_id);

        if ($request->transport_type) {
            $query->where('transport_type', $request->transport_type);
        }

        if ($request->pickup_location) {
            $query->where('pickup_location', $request->pickup_location);
        }

        $transports = $query->paginate($request->per_page ?? 20);

        return response()->json(['data' => $transports->items()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transport_type' => 'required|in:bus,car,van,coach',
            'vehicle_name' => 'required|string',
            'vehicle_number' => 'required|string',
            'pickup_location' => 'required|string',
            'dropoff_location' => 'required|string',
            'pickup_date' => 'required|datetime',
            'pickup_time' => 'required|date_format:H:i:s',
            'capacity' => 'required|integer|min:1',
            'price_per_seat' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'driver_name' => 'required|string',
            'driver_phone' => 'required|string',
        ]);

        $transport = Transport::create([
            'tenant_id' => $request->user->tenant_id,
            'status' => 'active',
            ...$validated
        ]);

        return response()->json(['data' => $transport], 201);
    }

    public function bookTransport(Request $request)
    {
        $validated = $request->validate([
            'transport_id' => 'required|exists:transports,id',
            'booking_id' => 'required|exists:bookings,id',
            'travelers' => 'required|array',
        ]);

        $transport = Transport::findOrFail($validated['transport_id']);

        $booked_count = $transport->transportBookings()->count();
        if ($booked_count + count($validated['travelers']) > $transport->capacity) {
            return response()->json(['error' => 'Not enough capacity'], 400);
        }

        foreach ($validated['travelers'] as $traveler_id) {
            TransportBooking::create([
                'booking_id' => $validated['booking_id'],
                'transport_id' => $transport->id,
                'booking_traveler_id' => $traveler_id,
                'status' => 'booked',
            ]);
        }

        return response()->json(['message' => 'Transport booked successfully'], 201);
    }
}
