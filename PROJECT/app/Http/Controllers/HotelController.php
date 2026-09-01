<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\HotelBooking;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function index(Request $request)
    {
        $query = Hotel::where('tenant_id', $request->user->tenant_id);

        if ($request->city) {
            $query->where('city', $request->city);
        }

        if ($request->star_rating) {
            $query->where('star_rating', '>=', $request->star_rating);
        }

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $hotels = $query->paginate($request->per_page ?? 20);

        return response()->json(['data' => $hotels->items()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'city' => 'required|string',
            'country' => 'required|string',
            'address' => 'required|string',
            'phone' => 'required|string',
            'email' => 'required|email',
            'website' => 'nullable|url',
            'star_rating' => 'required|integer|min:1|max:5',
            'total_rooms' => 'required|integer|min:1',
            'price_per_night' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
        ]);

        $hotel = Hotel::create([
            'tenant_id' => $request->user->tenant_id,
            'available_rooms' => $validated['total_rooms'],
            'status' => 'active',
            ...$validated
        ]);

        return response()->json(['data' => $hotel], 201);
    }

    public function show(Request $request, $id)
    {
        $hotel = Hotel::where('tenant_id', $request->user->tenant_id)
            ->with('hotelBookings')
            ->findOrFail($id);

        return response()->json(['data' => $hotel]);
    }

    public function bookHotel(Request $request)
    {
        $validated = $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
            'booking_id' => 'required|exists:bookings,id',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'number_of_rooms' => 'required|integer|min:1',
            'room_type' => 'required|string',
        ]);

        $hotel = Hotel::findOrFail($validated['hotel_id']);

        if ($hotel->available_rooms < $validated['number_of_rooms']) {
            return response()->json(['error' => 'Not enough rooms available'], 400);
        }

        $nights = now()->parse($validated['check_in_date'])
            ->diffInDays(now()->parse($validated['check_out_date']));

        $booking = HotelBooking::create([
            'booking_id' => $validated['booking_id'],
            'hotel_id' => $hotel->id,
            'check_in_date' => $validated['check_in_date'],
            'check_out_date' => $validated['check_out_date'],
            'number_of_rooms' => $validated['number_of_rooms'],
            'number_of_nights' => $nights,
            'room_type' => $validated['room_type'],
            'price_per_night' => $hotel->price_per_night,
            'total_price' => $hotel->price_per_night * $nights * $validated['number_of_rooms'],
            'status' => 'confirmed',
        ]);

        $hotel->decrement('available_rooms', $validated['number_of_rooms']);

        return response()->json(['data' => $booking], 201);
    }
}
