<?php

namespace App\Http\Controllers;

use App\Models\GroupBooking;
use App\Models\Booking;
use Illuminate\Http\Request;

class GroupBookingController extends Controller
{
    public function index(Request $request)
    {
        $groups = GroupBooking::where('tenant_id', $request->user->tenant_id)
            ->with('groupLeader', 'bookings')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $groups->items(),
            'pagination' => [
                'total' => $groups->total(),
                'per_page' => $groups->perPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'group_name' => 'required|string|max:255',
            'group_leader_id' => 'required|exists:customers,id',
            'total_members' => 'required|integer|min:2',
            'description' => 'nullable|string',
        ]);

        $group = GroupBooking::create([
            'tenant_id' => $request->user->tenant_id,
            'created_by' => $request->user->id,
            'status' => 'active',
            ...$validated
        ]);

        return response()->json([
            'message' => 'Group created successfully',
            'data' => $group
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $group = GroupBooking::where('tenant_id', $request->user->tenant_id)
            ->with('groupLeader', 'bookings.travelers')
            ->findOrFail($id);

        $stats = [
            'total_bookings' => $group->getBookingCount(),
            'total_travelers' => $group->getTotalTravelers(),
            'confirmed_bookings' => $group->bookings()
                ->where('status', 'confirmed')->count(),
        ];

        return response()->json([
            'data' => $group,
            'stats' => $stats
        ]);
    }

    public function addBookingToGroup(Request $request, $groupId)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $group = GroupBooking::where('tenant_id', $request->user->tenant_id)
            ->findOrFail($groupId);

        $booking = Booking::findOrFail($validated['booking_id']);
        $booking->update(['group_booking_id' => $group->id]);

        return response()->json([
            'message' => 'Booking added to group',
            'data' => $booking
        ]);
    }

    public function getGroupBookings(Request $request, $groupId)
    {
        $bookings = Booking::where('group_booking_id', $groupId)
            ->with('customer', 'travelers')
            ->get();

        return response()->json(['data' => $bookings]);
    }

    public function getGroupStats(Request $request, $groupId)
    {
        $group = GroupBooking::where('tenant_id', $request->user->tenant_id)
            ->findOrFail($groupId);

        $stats = [
            'group_name' => $group->group_name,
            'total_members' => $group->total_members,
            'total_bookings' => $group->getBookingCount(),
            'total_travelers' => $group->getTotalTravelers(),
            'confirmed_bookings' => $group->bookings()
                ->where('status', 'confirmed')->count(),
            'pending_bookings' => $group->bookings()
                ->where('status', 'pending')->count(),
            'total_revenue' => $group->bookings()
                ->where('status', 'confirmed')->sum('total_amount'),
        ];

        return response()->json(['data' => $stats]);
    }
}
