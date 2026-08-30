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
            'hotel_room_type_id' => 'nullable|exists:hotel_room_types,id',
            'booking_id' => 'required|exists:bookings,id',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'number_of_rooms' => 'required|integer|min:1',
            'room_type' => 'required|string',
            'extra_service_ids' => 'nullable|array',
            'extra_service_ids.*' => 'exists:hotel_extra_services,id',
        ]);

        $hotel = \App\Models\Hotel::where('tenant_id', $request->user->tenant_id)->findOrFail($validated['hotel_id']);

        $roomType = null;
        $pricePerNight = $hotel->price_per_night;
        if (!empty($validated['hotel_room_type_id'])) {
            $roomType = \App\Models\HotelRoomType::where('hotel_id', $hotel->id)->findOrFail($validated['hotel_room_type_id']);
            if (!$roomType->isAvailable($validated['number_of_rooms'])) {
                return response()->json(['error' => 'Not enough rooms available for this room type'], 400);
            }
            $pricePerNight = $roomType->price_per_night;
        } elseif ($hotel->available_rooms < $validated['number_of_rooms']) {
            return response()->json(['error' => 'Not enough rooms available'], 400);
        }

        $nights = now()->parse($validated['check_in_date'])
            ->diffInDays(now()->parse($validated['check_out_date']));

        $totalPrice = $pricePerNight * $nights * $validated['number_of_rooms'];

        $booking = HotelBooking::create([
            'booking_id' => $validated['booking_id'],
            'hotel_id' => $hotel->id,
            'hotel_room_type_id' => $roomType?->id,
            'check_in_date' => $validated['check_in_date'],
            'check_out_date' => $validated['check_out_date'],
            'number_of_rooms' => $validated['number_of_rooms'],
            'number_of_nights' => $nights,
            'room_type' => $validated['room_type'],
            'price_per_night' => $pricePerNight,
            'total_price' => $totalPrice,
            'status' => 'confirmed',
        ]);

        foreach ($validated['extra_service_ids'] ?? [] as $serviceId) {
            $service = \App\Models\HotelExtraService::where('hotel_id', $hotel->id)->findOrFail($serviceId);
            \App\Models\HotelBookingExtraService::create([
                'hotel_booking_id' => $booking->id,
                'hotel_extra_service_id' => $service->id,
                'quantity' => 1,
                'price' => $service->price,
            ]);
        }

        if ($roomType) {
            $roomType->decrement('available_rooms', $validated['number_of_rooms']);
        } else {
            $hotel->decrement('available_rooms', $validated['number_of_rooms']);
        }

        return response()->json(['data' => $booking->load('extraServices')], 201);
    }
}
