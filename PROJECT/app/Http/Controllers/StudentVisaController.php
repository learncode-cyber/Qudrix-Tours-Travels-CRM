<?php
namespace App\Http\Controllers;
use App\Models\StudentVisaApplication;
use Illuminate\Http\Request;

class StudentVisaController extends Controller
{
    public function index(Request $request)
    {
        $applications = StudentVisaApplication::where('tenant_id', $request->user->tenant_id)
            ->when($request->application_status, fn ($q) => $q->where('application_status', $request->application_status))
            ->when($request->assigned_counsellor_id, fn ($q) => $q->where('assigned_counsellor_id', $request->assigned_counsellor_id))
            ->when($request->destination_country, fn ($q) => $q->where('destination_country', $request->destination_country))
            ->latest()
            ->paginate($request->per_page ?? 20);
        return response()->json(['data' => $applications->items()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'nullable|exists:leads,id',
            'customer_id' => 'nullable|exists:customers,id',
            'student_name' => 'required|string',
            'date_of_birth' => 'nullable|date',
            'destination_country' => 'required|string|size:2',
            'university' => 'nullable|string',
            'course' => 'nullable|string',
            'intake' => 'nullable|string',
            'assigned_counsellor_id' => 'nullable|exists:users,id',
            'service_fee' => 'nullable|numeric|min:0',
            'service_fee_currency' => 'nullable|string|size:3',
        ]);
        $application = StudentVisaApplication::create([
            'tenant_id' => $request->user->tenant_id,
            'application_status' => 'inquiry',
            'visa_status' => 'not_applied',
            'payment_status' => 'pending',
            'service_fee_currency' => $validated['service_fee_currency'] ?? 'USD',
            ...$validated,
        ]);
        return response()->json(['data' => $application], 201);
    }

    public function show(Request $request, $id)
    {
        $application = StudentVisaApplication::where('tenant_id', $request->user->tenant_id)
            ->with('lead', 'customer', 'counsellor')
            ->findOrFail($id);
        return response()->json(['data' => $application]);
    }

    public function update(Request $request, $id)
    {
        $application = StudentVisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'university' => 'nullable|string',
            'course' => 'nullable|string',
            'intake' => 'nullable|string',
            'notes' => 'nullable|string',
            'service_fee' => 'nullable|numeric|min:0',
        ]);
        $application->update($validated);
        return response()->json(['data' => $application]);
    }

    public function updateStatus(Request $request, $id)
    {
        $application = StudentVisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'application_status' => 'required|in:inquiry,documents_pending,applied,offer_received,visa_appointment_scheduled,visa_submitted,visa_approved,visa_rejected,enrolled',
        ]);
        $application->update($validated);
        return response()->json(['data' => $application]);
    }

    public function recordOfferLetter(Request $request, $id)
    {
        $application = StudentVisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate(['offer_letter_date' => 'required|date']);
        $application->update([
            'offer_letter_received' => true,
            'offer_letter_date' => $validated['offer_letter_date'],
            'application_status' => 'offer_received',
        ]);
        return response()->json(['data' => $application]);
    }

    public function scheduleEmbassyAppointment(Request $request, $id)
    {
        $application = StudentVisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate(['embassy_appointment_date' => 'required|date']);
        $application->update([
            'embassy_appointment_date' => $validated['embassy_appointment_date'],
            'application_status' => 'visa_appointment_scheduled',
        ]);
        return response()->json(['data' => $application]);
    }

    public function updateVisaStatus(Request $request, $id)
    {
        $application = StudentVisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate(['visa_status' => 'required|in:not_applied,submitted,approved,rejected']);
        $application->update([
            'visa_status' => $validated['visa_status'],
            'application_status' => match ($validated['visa_status']) {
                'submitted' => 'visa_submitted',
                'approved' => 'visa_approved',
                'rejected' => 'visa_rejected',
                default => $application->application_status,
            },
        ]);
        return response()->json(['data' => $application]);
    }

    public function assignCounsellor(Request $request, $id)
    {
        $application = StudentVisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate(['assigned_counsellor_id' => 'required|exists:users,id']);
        $application->update($validated);
        return response()->json(['data' => $application]);
    }
}
