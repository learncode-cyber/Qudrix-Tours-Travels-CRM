<?php

namespace App\Http\Controllers;

use App\Models\RoomBlock;
use Illuminate\Http\Request;

// Room blocks hold hotel inventory for a group before individual
// bookings are made against it — deliberately separate from
// HotelController::bookHotel's per-guest booking flow and does not
// mutate HotelRoomType::available_rooms itself (that counter is owned
// by the existing, already-tested booking path). This is an explicit
// allotment ledger staff manage directly: how many rooms were held,
// how many have been released back, and what's left.
class RoomBlockController extends Controller
{
    public function index(Request $request)
    {
        $query = RoomBlock::where('tenant_id', $request->user->tenant_id)
            ->with(['hotel', 'roomType', 'groupBooking']);

        if ($request->hotel_id) {
            $query->where('hotel_id', $request->hotel_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $blocks = $query->orderBy('start_date', 'desc')->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $blocks->items(),
            'pagination' => [
                'total' => $blocks->total(),
                'per_page' => $blocks->perPage(),
                'current_page' => $blocks->currentPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
            'hotel_room_type_id' => 'required|exists:hotel_room_types,id',
            'group_booking_id' => 'nullable|exists:group_bookings,id',
            'name' => 'nullable|string|max:255',
            'blocked_rooms' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'notes' => 'nullable|string',
        ]);

        $block = RoomBlock::create([
            'tenant_id' => $request->user->tenant_id,
            'status' => 'held',
            'released_rooms' => 0,
            ...$validated,
        ]);

        return response()->json(['message' => 'Room block created successfully', 'data' => $block->load(['hotel', 'roomType'])], 201);
    }

    public function show(Request $request, $id)
    {
        $block = RoomBlock::where('tenant_id', $request->user->tenant_id)
            ->with(['hotel', 'roomType', 'groupBooking'])
            ->findOrFail($id);

        return response()->json(['data' => $block]);
    }

    // Releases some (or all) of a block's held rooms back to general
    // availability bookkeeping. Never releases more than remain blocked.
    public function release(Request $request, $id)
    {
        $block = RoomBlock::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'rooms' => 'required|integer|min:1|max:' . max($block->remainingRooms(), 1),
        ]);

        if ($validated['rooms'] > $block->remainingRooms()) {
            return response()->json(['error' => 'Cannot release more rooms than remain blocked'], 400);
        }

        $block->increment('released_rooms', $validated['rooms']);
        $block->refresh();
        $block->update([
            'status' => $block->remainingRooms() === 0 ? 'released' : 'partially_released',
        ]);

        return response()->json(['message' => 'Rooms released', 'data' => $block]);
    }

    public function destroy(Request $request, $id)
    {
        $block = RoomBlock::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $block->delete();

        return response()->json(['message' => 'Room block deleted successfully']);
    }
}
