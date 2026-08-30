<?php
namespace App\Http\Controllers;
use App\Models\Hotel;
use App\Models\HotelExtraService;
use Illuminate\Http\Request;

class HotelExtraServiceController extends Controller
{
    public function index(Request $request, $hotelId)
    {
        $hotel = Hotel::where('tenant_id', $request->user->tenant_id)->findOrFail($hotelId);
        return response()->json(['data' => $hotel->extraServices]);
    }
    public function store(Request $request, $hotelId)
    {
        $hotel = Hotel::where('tenant_id', $request->user->tenant_id)->findOrFail($hotelId);
        $validated = $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
        ]);
        $service = HotelExtraService::create([
            'tenant_id' => $request->user->tenant_id,
            'hotel_id' => $hotel->id,
            'is_active' => true,
            ...$validated,
        ]);
        return response()->json(['data' => $service], 201);
    }
    public function destroy(Request $request, $hotelId, $id)
    {
        $service = HotelExtraService::where('tenant_id', $request->user->tenant_id)
            ->where('hotel_id', $hotelId)
            ->findOrFail($id);
        $service->delete();
        return response()->json(['message' => 'Extra service deleted']);
    }
}
