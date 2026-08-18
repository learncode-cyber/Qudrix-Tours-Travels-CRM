<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\BookingItinerary;
use App\Models\BookingConfirmation;
use App\Models\GroupBooking;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::where('tenant_id', $request->user->tenant_id)
            ->with('customer', 'package', 'travelers', 'itinerary');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->booking_type) {
            $query->where('booking_type', $request->booking_type);
        }

        if ($request->search) {
            $query->where('booking_number', 'like', "%{$request->search}%");
        }

        $bookings = $query->orderBy('travel_date', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $bookings->items(),
            'pagination' => [
                'total' => $bookings->total(),
                'per_page' => $bookings->perPage(),
                'current_page' => $bookings->currentPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'nullable|exists:leads,id',
            'customer_id' => 'required|exists:customers,id',
            'package_id' => 'required|exists:packages,id',
            'booking_type' => 'required|in:individual,group,corporate',
            'travel_date' => 'required|date|after:today',
            'return_date' => 'required|date|after:travel_date',
            'number_of_travelers' => 'required|integer|min:1',
            'total_amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'visa_required' => 'nullable|boolean',
            'special_requests' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $booking = Booking::create([
            'tenant_id' => $request->user->tenant_id,
            'created_by' => $request->user->id,
            'booking_number' => 'BK-' . time(),
            'status' => 'pending',
            'payment_status' => 'pending',
            ...$validated
        ]);

        return response()->json([
            'message' => 'Booking created successfully',
            'data' => $booking
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $booking = Booking::where('tenant_id', $request->user->tenant_id)
            ->with('customer', 'package', 'travelers', 'itinerary', 'confirmation')
            ->findOrFail($id);

        return response()->json(['data' => $booking]);
    }

    public function update(Request $request, $id)
    {
        $booking = Booking::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($booking->status === 'confirmed') {
            return response()->json(['error' => 'Cannot update confirmed booking'], 400);
        }

        $validated = $request->validate([
            'travel_date' => 'sometimes|date|after:today',
            'return_date' => 'sometimes|date',
            'special_requests' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $booking->update($validated);

        return response()->json([
            'message' => 'Booking updated',
            'data' => $booking
        ]);
    }

    public function confirmBooking(Request $request, $id)
    {
        $booking = Booking::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($booking->status === 'confirmed') {
            return response()->json(['error' => 'Booking already confirmed'], 400);
        }

        $booking->markAsConfirmed();

        BookingConfirmation::create([
            'tenant_id' => $request->user->tenant_id,
            'booking_id' => $booking->id,
            'confirmation_number' => 'CONF-' . time(),
            'confirmation_date' => now(),
            'confirmed_by' => $request->user->id,
            'confirmation_method' => 'system',
        ]);

        return response()->json([
            'message' => 'Booking confirmed successfully',
            'data' => $booking
        ]);
    }

    public function cancelBooking(Request $request, $id)
    {
        $booking = Booking::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $booking->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Booking cancelled',
            'data' => $booking
        ]);
    }

    public function getBookingStats(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $stats = [
            'total' => Booking::where('tenant_id', $tenantId)->count(),
            'pending' => Booking::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
            'confirmed' => Booking::where('tenant_id', $tenantId)->where('status', 'confirmed')->count(),
            'cancelled' => Booking::where('tenant_id', $tenantId)->where('status', 'cancelled')->count(),
            'total_travelers' => BookingTraveler::whereIn('booking_id', 
                Booking::where('tenant_id', $tenantId)->pluck('id'))->count(),
            'total_revenue' => Booking::where('tenant_id', $tenantId)
                ->where('status', 'confirmed')->sum('total_amount'),
            'upcoming_bookings' => Booking::where('tenant_id', $tenantId)
                ->where('travel_date', '>', now())
                ->where('status', 'confirmed')
                ->count(),
        ];

        return response()->json(['data' => $stats]);
    }
}
