<?php

namespace App\Http\Controllers;

use App\Models\StudentVisaApplication;
use Illuminate\Http\Request;

class StudentVisaController extends Controller
{
    public function index(Request $request)
    {
        $query = StudentVisaApplication::where('tenant_id', $request->user->tenant_id)
            ->with('counselor');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->assigned_to) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->deadline_soon) {
            $query->whereNotNull('application_deadline')
                ->where('application_deadline', '>=', now())
                ->where('application_deadline', '<=', now()->addDays(14));
        }

        $applications = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $applications->items(),
            'pagination' => [
                'total' => $applications->total(),
                'per_page' => $applications->perPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'nullable|exists:leads,id',
            'customer_id' => 'nullable|exists:customers,id',
            'student_name' => 'required|string|max:255',
            'student_email' => 'nullable|email',
            'student_phone' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'nationality' => 'nullable|string',
            'passport_number' => 'nullable|string',
            'destination_country' => 'required|string',
            'is_al_azhar' => 'sometimes|boolean',
            'azhar_faculty' => 'nullable|string|required_if:is_al_azhar,true',
            'azhar_level' => 'nullable|in:preparatory,bachelor,masters,phd,arabic_language_institute',
            'azhar_registration_number' => 'nullable|string',
            'arabic_proficiency_level' => 'nullable|in:none,basic,intermediate,advanced,native',
            'university' => 'nullable|string',
            'course' => 'nullable|string',
            'intake' => 'nullable|string',
            'application_deadline' => 'nullable|date',
            'counselling_notes' => 'nullable|string',
        ]);

        $application = StudentVisaApplication::create([
            'tenant_id' => $request->user->tenant_id,
            'status' => 'inquiry',
            'visa_status' => 'not_applied',
            ...$validated,
        ]);

        return response()->json(['data' => $application], 201);
    }

    public function show(Request $request, $id)
    {
        $application = StudentVisaApplication::where('tenant_id', $request->user->tenant_id)
            ->with('counselor', 'lead', 'customer')
            ->findOrFail($id);

        return response()->json([
            'data' => $application,
            'deadline_approaching' => $application->isDeadlineApproaching(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $application = StudentVisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'student_name' => 'sometimes|string|max:255',
            'student_email' => 'nullable|email',
            'student_phone' => 'nullable|string',
            'is_al_azhar' => 'sometimes|boolean',
            'azhar_faculty' => 'nullable|string',
            'azhar_level' => 'nullable|in:preparatory,bachelor,masters,phd,arabic_language_institute',
            'azhar_registration_number' => 'nullable|string',
            'arabic_proficiency_level' => 'nullable|in:none,basic,intermediate,advanced,native',
            'university' => 'nullable|string',
            'course' => 'nullable|string',
            'intake' => 'nullable|string',
            'application_deadline' => 'nullable|date',
            'appointment_date' => 'nullable|date',
            'counselling_notes' => 'nullable|string',
        ]);

        $application->update($validated);

        return response()->json(['data' => $application]);
    }

    public function assignCounselor(Request $request, $id)
    {
        $validated = $request->validate(['user_id' => 'required|exists:users,id']);

        $application = StudentVisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $application->update(['assigned_to' => $validated['user_id']]);

        return response()->json(['data' => $application->load('counselor')]);
    }

    /**
     * Advances application status. Enforces the sequence rather than
     * allowing an arbitrary jump (e.g. straight to 'enrolled' from
     * 'inquiry'), matching the guarded-transition pattern used for
     * Quotation/Proposal/Visa elsewhere in this app.
     */
    public function advanceStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:documents_pending,application_submitted,offer_received,offer_accepted,visa_applied,visa_approved,visa_rejected,enrolled,withdrawn',
        ]);

        $application = StudentVisaApplication::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $sequence = [
            'inquiry', 'documents_pending', 'application_submitted', 'offer_received',
            'offer_accepted', 'visa_applied', 'visa_approved', 'enrolled',
        ];

        $currentIndex = array_search($application->status, $sequence);
        $targetIndex = array_search($validated['status'], $sequence);

        // 'withdrawn' and 'visa_rejected' are exit states reachable from
        // anywhere; otherwise only allow moving forward one step at a time.
        if (!in_array($validated['status'], ['withdrawn', 'visa_rejected'])) {
            if ($currentIndex === false || $targetIndex === false || $targetIndex !== $currentIndex + 1) {
                return response()->json([
                    'error' => "Cannot move from '{$application->status}' directly to '{$validated['status']}'",
                    'expected_next' => $sequence[$currentIndex + 1] ?? null,
                ], 400);
            }
        }

        $updates = ['status' => $validated['status']];

        if ($validated['status'] === 'offer_received') {
            $updates['offer_letter_received'] = true;
            $updates['offer_letter_date'] = now();
        }

        if ($validated['status'] === 'visa_applied') {
            $updates['visa_status'] = 'applied';
        } elseif ($validated['status'] === 'visa_approved') {
            $updates['visa_status'] = 'approved';
            $updates['visa_decision_date'] = now();
        } elseif ($validated['status'] === 'visa_rejected') {
            $updates['visa_status'] = 'rejected';
            $updates['visa_decision_date'] = now();
        }

        $application->update($updates);

        return response()->json(['data' => $application]);
    }

    public function getStats(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        return response()->json(['data' => [
            'total' => StudentVisaApplication::where('tenant_id', $tenantId)->count(),
            'by_status' => StudentVisaApplication::where('tenant_id', $tenantId)
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'deadline_approaching' => StudentVisaApplication::where('tenant_id', $tenantId)
                ->whereNotNull('application_deadline')
                ->where('application_deadline', '>=', now())
                ->where('application_deadline', '<=', now()->addDays(14))
                ->count(),
            'visa_approved' => StudentVisaApplication::where('tenant_id', $tenantId)->where('visa_status', 'approved')->count(),
        ]]);
    }
}
