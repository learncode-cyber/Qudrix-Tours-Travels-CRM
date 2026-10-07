<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\BookingItinerary;
use App\Models\BookingConfirmation;
use App\Models\GroupBooking;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(protected NotificationService $notifications)
    {
    }

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

    /**
     * PHASE 8: real chain link — turns a signed Proposal (which is now
     * linked to a real Customer, per this phase's ProposalController fix)
     * into an operational Booking, closing the "Deal -> Quotation ->
     * Booking" step that previously had no code path connecting them at
     * all.
     */
    public function createFromProposal(Request $request)
    {
        $validated = $request->validate([
            'proposal_id' => 'required|exists:proposals,id',
            'travel_date' => 'required|date|after:today',
            'return_date' => 'required|date|after:travel_date',
        ]);

        $tenantId = $request->user->tenant_id;

        $proposal = \App\Models\Proposal::where('tenant_id', $tenantId)
            ->with('quotation')
            ->findOrFail($validated['proposal_id']);

        if ($proposal->status !== 'signed') {
            return response()->json(['error' => 'Booking can only be created from a signed proposal'], 400);
        }

        if (!$proposal->customer_id) {
            return response()->json(['error' => 'Proposal has no linked customer yet'], 400);
        }

        $quotation = $proposal->quotation;

        $booking = Booking::create([
            'tenant_id' => $tenantId,
            'created_by' => $request->user->id,
            'lead_id' => $proposal->lead_id,
            'customer_id' => $proposal->customer_id,
            'package_id' => $quotation?->package_id,
            'quotation_id' => $quotation?->id,
            'proposal_id' => $proposal->id,
            'booking_number' => 'BK-' . time(),
            'booking_type' => 'individual',
            'status' => 'pending',
            'payment_status' => 'pending',
            'travel_date' => $validated['travel_date'],
            'return_date' => $validated['return_date'],
            'number_of_travelers' => $quotation->number_of_travelers ?? 1,
            'total_amount' => $quotation->total_amount ?? 0,
            'currency' => $quotation->currency ?? 'USD',
        ]);

        return response()->json([
            'message' => 'Booking created from signed proposal',
            'data' => $booking,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $booking = Booking::where('tenant_id', $request->user->tenant_id)
            ->with('customer', 'package', 'travelers', 'itinerary', 'confirmation', 'hotelBookings.hotel', 'hotelBookings.roomAssignments.travelers', 'flightBookings.flight', 'flightBookings.traveler', 'transportBookings.transport', 'transportBookings.traveler')
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

        // PHASE 7: real notification dispatch, not a fake "sent" flag —
        // the result honestly reflects whether email is actually
        // configured for this tenant (see EmailChannel).
        $notifyResult = null;
        if ($booking->customer && $booking->customer->email) {
            $notifyResult = $this->notifications->notify(
                $request->user->tenant_id,
                'booking_confirmed',
                'email',
                ['to' => $booking->customer->email, 'customer_id' => $booking->customer_id],
                [
                    'customer_name' => $booking->customer->name,
                    'booking_number' => $booking->booking_number,
                    'travel_date' => optional($booking->travel_date)->toDateString(),
                    'default_message' => "Your booking {$booking->booking_number} is confirmed.",
                ],
                ['type' => 'booking', 'id' => $booking->id]
            )['result'];
        }

        return response()->json([
            'message' => 'Booking confirmed successfully',
            'data' => $booking,
            'notification' => $notifyResult,
        ]);
    }

    public function cancelBooking(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string',
            'refund_amount' => 'nullable|numeric|min:0',
        ]);

        $booking = Booking::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($booking->status === 'cancelled') {
            return response()->json(['error' => 'Booking already cancelled'], 400);
        }

        $booking->update([
            'status' => 'cancelled',
            'notes' => trim(($booking->notes ?? '') . "\n[Cancelled] " . ($validated['reason'] ?? '')),
        ]);

        // PHASE 4: refund is a real Payment record (status=refunded), not
        // just a status flip — so it shows up in payment stats/history
        // rather than silently vanishing.
        $refund = null;
        if (!empty($validated['refund_amount'])) {
            $refund = \App\Models\Payment::create([
                'tenant_id' => $request->user->tenant_id,
                'booking_id' => $booking->id,
                'amount' => $validated['refund_amount'],
                'payment_method' => 'refund',
                'status' => 'refunded',
                'paid_at' => now(),
                'notes' => 'Refund for cancelled booking ' . $booking->booking_number,
            ]);
            $booking->update(['payment_status' => 'refunded']);
        }

        return response()->json([
            'message' => 'Booking cancelled',
            'data' => $booking,
            'refund' => $refund,
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
