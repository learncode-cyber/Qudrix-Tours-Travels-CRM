<?php

namespace App\Http\Controllers;

use App\Models\VisaApplication;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;

class VisaController extends Controller
{
    public function __construct(protected NotificationService $notifications)
    {
    }

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
            'embassy_name' => 'nullable|string',
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
            ->with('booking', 'traveler', 'assignedStaff')
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

    public function scheduleAppointment(Request $request, $id)
    {
        $validated = $request->validate([
            'appointment_date' => 'required|date|after:now',
            'embassy_name' => 'nullable|string',
        ]);

        $visa = VisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $visa->update($validated);

        return response()->json(['message' => 'Appointment scheduled', 'data' => $visa]);
    }

    public function assignStaff(Request $request, $id)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $visa = VisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $visa->update(['assigned_to' => $validated['user_id']]);

        return response()->json(['message' => 'Staff assigned', 'data' => $visa->load('assignedStaff')]);
    }

    public function approveVisa(Request $request, $id)
    {
        $validated = $request->validate([
            'visa_number' => 'required|string',
            'issue_date' => 'required|date',
            'expiry_date' => 'required|date|after:issue_date',
        ]);

        $visa = VisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($visa->status !== 'submitted') {
            return response()->json(['error' => 'Visa must be submitted before it can be approved'], 400);
        }

        $visa->update([
            'approval_date' => now(),
            'status' => 'approved',
            ...$validated
        ]);

        $this->notifyVisaStatus($visa, 'visa_approved');

        return response()->json(['data' => $visa]);
    }

    public function rejectVisa(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $visa = VisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($visa->status !== 'submitted') {
            return response()->json(['error' => 'Visa must be submitted before it can be rejected'], 400);
        }

        $visa->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $this->notifyVisaStatus($visa, 'visa_rejected');

        return response()->json(['data' => $visa]);
    }

    /**
     * PHASE 7: notify the customer behind this visa's booking, if one
     * exists and has an email on file. Same honest delivered/blocked
     * reporting as booking confirmations.
     */
    protected function notifyVisaStatus(VisaApplication $visa, string $eventKey): void
    {
        $customer = $visa->booking?->customer;
        if (!$customer || !$customer->email) {
            return;
        }

        $this->notifications->notify(
            $visa->tenant_id,
            $eventKey,
            'email',
            ['to' => $customer->email, 'customer_id' => $customer->id],
            [
                'customer_name' => $customer->name,
                'destination_country' => $visa->destination_country,
                'visa_type' => $visa->visa_type,
                'default_message' => "Your {$visa->visa_type} visa application for {$visa->destination_country} has been " . str_replace('visa_', '', $eventKey) . '.',
            ],
            ['type' => 'visa_application', 'id' => $visa->id]
        );
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
