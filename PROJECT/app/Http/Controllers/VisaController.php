<?php

namespace App\Http\Controllers;

use App\Models\VisaApplication;
use Illuminate\Http\Request;

class VisaController extends Controller
{
    public function index(Request $request)
    {
        $query = VisaApplication::where('tenant_id', $request->user->tenant_id);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->booking_id) {
            $query->where('booking_id', $request->booking_id);
        }

        $visas = $query->paginate($request->per_page ?? 20);

        return response()->json(['data' => $visas->items()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'booking_traveler_id' => 'required|exists:booking_travelers,id',
            'destination_country' => 'required|string|size:2',
            'visa_type' => 'required|string',
            'agency_name' => 'nullable|string',
        ]);

        $visa = VisaApplication::create([
            'tenant_id' => $request->user->tenant_id,
            'application_date' => now(),
            'status' => 'pending',
            ...$validated
        ]);

        return response()->json(['data' => $visa], 201);
    }

    public function show(Request $request, $id)
    {
        $visa = VisaApplication::where('tenant_id', $request->user->tenant_id)
            ->with('booking', 'traveler')
            ->findOrFail($id);

        return response()->json(['data' => $visa]);
    }

    public function submitApplication(Request $request, $id)
    {
        $visa = VisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $visa->update([
            'submission_date' => now(),
            'status' => 'submitted'
        ]);

        return response()->json(['data' => $visa]);
    }

    public function approveVisa(Request $request, $id)
    {
        $validated = $request->validate([
            'visa_number' => 'required|string',
            'issue_date' => 'required|date',
            'expiry_date' => 'required|date|after:issue_date',
        ]);

        $visa = VisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $visa->update([
            'approval_date' => now(),
            'status' => 'approved',
            ...$validated
        ]);

        return response()->json(['data' => $visa]);
    }

    public function getVisaStatus(Request $request, $bookingId)
    {
        $visas = VisaApplication::where('tenant_id', $request->user->tenant_id)
            ->where('booking_id', $bookingId)
            ->get();

        $status = [
            'total_travelers' => $visas->count(),
            'approved' => $visas->where('status', 'approved')->count(),
            'pending' => $visas->where('status', 'pending')->count(),
            'submitted' => $visas->where('status', 'submitted')->count(),
            'expired' => $visas->filter(function ($visa) { return $visa->isExpired(); })->count(),
        ];

        return response()->json(['data' => $status]);
    }
}
