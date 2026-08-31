<?php

namespace App\Http\Controllers;

use App\Models\VisaApplication;
use App\Models\VisaChecklistItem;
use App\Models\VisaDocumentRequirement;
use App\Services\ExpiryReminderService;
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
            'embassy' => 'nullable|string',
            'embassy_id' => 'nullable|exists:embassies,id',
            'agency_name' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $visa = VisaApplication::create([
            'tenant_id' => $request->user->tenant_id,
            'application_date' => now(),
            'status' => 'pending',
            ...$validated
        ]);

        // Seed the document checklist from the configurable per-country/
        // type requirements, if any have been defined.
        $requirements = VisaDocumentRequirement::where('tenant_id', $request->user->tenant_id)
            ->where('destination_country', $validated['destination_country'])
            ->where('visa_type', $validated['visa_type'])
            ->get();
        foreach ($requirements as $requirement) {
            VisaChecklistItem::create([
                'visa_application_id' => $visa->id,
                'visa_document_requirement_id' => $requirement->id,
                'document_name' => $requirement->document_name,
                'status' => 'missing',
            ]);
        }

        return response()->json(['data' => $visa->load('checklistItems')], 201);
    }

    public function checklist(Request $request, $id)
    {
        $visa = VisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        return response()->json(['data' => $visa->checklistItems]);
    }

    public function updateChecklistItem(Request $request, $id, $itemId)
    {
        $visa = VisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $item = VisaChecklistItem::where('visa_application_id', $visa->id)->findOrFail($itemId);
        $validated = $request->validate([
            'status' => 'required|in:missing,submitted,verified,rejected',
        ]);
        $update = ['status' => $validated['status']];
        if ($validated['status'] === 'submitted') {
            $update['submitted_at'] = now();
        } elseif ($validated['status'] === 'verified') {
            $update['verified_at'] = now();
        }
        $item->update($update);
        return response()->json(['data' => $item]);
    }

    public function assign(Request $request, $id)
    {
        $visa = VisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate(['assigned_to' => 'required|exists:users,id']);
        $visa->update($validated);
        return response()->json(['data' => $visa]);
    }

    public function show(Request $request, $id)
    {
        $visa = VisaApplication::where('tenant_id', $request->user->tenant_id)
            ->with('booking', 'traveler')
            ->findOrFail($id);

        return response()->json(['data' => $visa]);
    }

    // apiResource('visas', ...) registers PUT/PATCH and DELETE
    // /visas/{visa} — these were missing entirely, which meant those two
    // routes 500'd with "method does not exist" the moment anything ever
    // called them. Added to match every other resource in this app.
    public function update(Request $request, $id)
    {
        $visa = VisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'destination_country' => 'sometimes|string|size:2',
            'visa_type' => 'sometimes|string',
            'embassy' => 'nullable|string',
            'embassy_id' => 'nullable|exists:embassies,id',
            'appointment_date' => 'nullable|date',
            'agency_name' => 'nullable|string',
            'agency_reference' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $visa->update($validated);

        return response()->json(['message' => 'Visa application updated successfully', 'data' => $visa]);
    }

    public function destroy(Request $request, $id)
    {
        $visa = VisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $visa->delete();

        return response()->json(['message' => 'Visa application deleted successfully']);
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

    // On-demand trigger for the same sweep the daily schedule runs
    // (routes/console.php) — scoped to this tenant only, and idempotent
    // (see ExpiryReminderService), so calling it twice in a row is safe.
    public function checkExpiryReminders(Request $request, ExpiryReminderService $service)
    {
        $days = (int) ($request->days ?? 90);
        $visaReminders = $service->checkVisaExpiries($request->user->tenant_id, $days);
        $passportReminders = $service->checkPassportExpiries($request->user->tenant_id, $days);

        return response()->json([
            'message' => 'Expiry check complete',
            'data' => [
                'visa_reminders_created' => $visaReminders->count(),
                'passport_reminders_created' => $passportReminders->count(),
            ],
        ]);
    }
}
