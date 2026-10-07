<?php

namespace App\Http\Controllers;

use App\Models\RitualCheckpoint;
use App\Models\Booking;
use Illuminate\Http\Request;

/**
 * PHASE 5: RitualCheckpoint (Hajj/Umrah pilgrim status tracking through
 * ritual stages — Ihram, Tawaf, Sa'i, etc.) has existed as a model since
 * an earlier phase but had no controller anywhere, so nothing could ever
 * create or update one.
 */
class RitualCheckpointController extends Controller
{
    public function index(Request $request, $bookingId)
    {
        $booking = Booking::where('tenant_id', $request->user->tenant_id)->findOrFail($bookingId);

        $checkpoints = RitualCheckpoint::where('booking_id', $booking->id)->get();

        return response()->json(['data' => $checkpoints]);
    }

    public function store(Request $request, $bookingId)
    {
        $booking = Booking::where('tenant_id', $request->user->tenant_id)->findOrFail($bookingId);

        $validated = $request->validate([
            'ritual_name' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $checkpoint = RitualCheckpoint::create([
            'booking_id' => $booking->id,
            'status' => 'pending',
            ...$validated,
        ]);

        return response()->json(['data' => $checkpoint], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $checkpoint = RitualCheckpoint::whereHas('booking', function ($q) use ($request) {
            $q->where('tenant_id', $request->user->tenant_id);
        })->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,completed',
            'notes' => 'nullable|string',
        ]);

        $checkpoint->update([
            ...$validated,
            'completed_date' => $validated['status'] === 'completed' ? now() : $checkpoint->completed_date,
        ]);

        return response()->json(['data' => $checkpoint]);
    }
}
