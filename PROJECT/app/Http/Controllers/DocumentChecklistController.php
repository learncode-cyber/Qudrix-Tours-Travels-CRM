<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequirement;
use App\Models\DocumentSubmission;
use Illuminate\Http\Request;

class DocumentChecklistController extends Controller
{
    public function indexRequirements(Request $request)
    {
        $query = DocumentRequirement::where('tenant_id', $request->user->tenant_id);

        if ($request->entity_type) {
            $query->where('entity_type', $request->entity_type);
        }

        return response()->json(['data' => $query->orderBy('sort_order')->get()]);
    }

    public function storeRequirement(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|string|max:100',
            'name' => 'required|string|max:255',
            'is_mandatory' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $requirement = DocumentRequirement::create([
            'tenant_id' => $request->user->tenant_id,
            ...$validated,
        ]);

        return response()->json(['data' => $requirement], 201);
    }

    /**
     * Readiness view for one entity instance: every requirement for its
     * entity_type, joined with whatever submission status exists (or
     * "pending" if none was ever created). This is what "document
     * readiness" (Hajj/Umrah spec) and "required documents" tracking
     * (Student Visa spec) both need.
     */
    public function readiness(Request $request, string $entityType, int $entityId)
    {
        $tenantId = $request->user->tenant_id;

        $requirements = DocumentRequirement::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->orderBy('sort_order')
            ->get();

        $submissions = DocumentSubmission::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->get()
            ->keyBy('document_requirement_id');

        $checklist = $requirements->map(function ($req) use ($submissions) {
            $submission = $submissions->get($req->id);
            return [
                'requirement_id' => $req->id,
                'name' => $req->name,
                'is_mandatory' => $req->is_mandatory,
                'status' => $submission->status ?? 'pending',
                'submission_id' => $submission->id ?? null,
                'rejection_reason' => $submission->rejection_reason ?? null,
            ];
        });

        $mandatoryTotal = $requirements->where('is_mandatory', true)->count();
        $mandatoryVerified = $checklist->filter(fn ($c) => $c['is_mandatory'] && $c['status'] === 'verified')->count();

        return response()->json([
            'data' => $checklist,
            'is_ready' => $mandatoryTotal > 0 && $mandatoryVerified === $mandatoryTotal,
            'mandatory_verified' => $mandatoryVerified,
            'mandatory_total' => $mandatoryTotal,
        ]);
    }

    public function submit(Request $request)
    {
        $validated = $request->validate([
            'document_requirement_id' => 'required|exists:document_requirements,id',
            'entity_type' => 'required|string',
            'entity_id' => 'required|integer',
            'file_reference' => 'nullable|string',
        ]);

        $submission = DocumentSubmission::updateOrCreate(
            [
                'tenant_id' => $request->user->tenant_id,
                'document_requirement_id' => $validated['document_requirement_id'],
                'entity_type' => $validated['entity_type'],
                'entity_id' => $validated['entity_id'],
            ],
            [
                'status' => 'submitted',
                'file_reference' => $validated['file_reference'] ?? null,
                'rejection_reason' => null,
            ]
        );

        return response()->json(['data' => $submission]);
    }

    public function verify(Request $request, $submissionId)
    {
        $submission = DocumentSubmission::where('tenant_id', $request->user->tenant_id)->findOrFail($submissionId);

        if ($submission->status !== 'submitted') {
            return response()->json(['error' => 'Document must be submitted before it can be verified'], 400);
        }

        $submission->update([
            'status' => 'verified',
            'verified_by' => $request->user->id,
            'verified_at' => now(),
        ]);

        return response()->json(['data' => $submission]);
    }

    public function reject(Request $request, $submissionId)
    {
        $validated = $request->validate(['rejection_reason' => 'required|string']);

        $submission = DocumentSubmission::where('tenant_id', $request->user->tenant_id)->findOrFail($submissionId);
        $submission->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json(['data' => $submission]);
    }
}
