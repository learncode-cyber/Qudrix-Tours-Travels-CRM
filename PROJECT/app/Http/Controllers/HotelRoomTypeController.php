<?php
namespace App\Http\Controllers;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use Illuminate\Http\Request;

class HotelRoomTypeController extends Controller
{
    public function index(Request $request, $hotelId)
    {
        $hotel = Hotel::where('tenant_id', $request->user->tenant_id)->findOrFail($hotelId);
        return response()->json(['data' => $hotel->roomTypes]);
    }
    public function store(Request $request, $hotelId)
    {
        $hotel = Hotel::where('tenant_id', $request->user->tenant_id)->findOrFail($hotelId);
        $validated = $request->validate([
            'name' => 'required|string',
            'capacity' => 'required|integer|min:1',
            'total_rooms' => 'required|integer|min:0',
            'price_per_night' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'amenities' => 'nullable|array',
        ]);
        $roomType = HotelRoomType::create([
            'tenant_id' => $request->user->tenant_id,
            'hotel_id' => $hotel->id,
            'available_rooms' => $validated['total_rooms'],
            ...$validated,
        ]);
        return response()->json(['data' => $roomType], 201);
    }
    public function update(Request $request, $hotelId, $id)
    {
        $roomType = HotelRoomType::where('tenant_id', $request->user->tenant_id)
            ->where('hotel_id', $hotelId)
            ->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'capacity' => 'sometimes|integer|min:1',
            'total_rooms' => 'sometimes|integer|min:0',
            'available_rooms' => 'sometimes|integer|min:0',
            'price_per_night' => 'sometimes|numeric|min:0',
            'amenities' => 'nullable|array',
        ]);
        $roomType->update($validated);
        return response()->json(['data' => $roomType]);
    }
    public function destroy(Request $request, $hotelId, $id)
    {
        $roomType = HotelRoomType::where('tenant_id', $request->user->tenant_id)
            ->where('hotel_id', $hotelId)
            ->findOrFail($id);
        $roomType->delete();
        return response()->json(['message' => 'Room type deleted']);
    }
}
