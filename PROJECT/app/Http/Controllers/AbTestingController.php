<?php
namespace App\Http\Controllers;

use App\Models\AbAssignment;
use App\Models\AbExperiment;
use App\Models\AbVariant;
use App\Models\Lead;
use App\Services\AbTestingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AbTestingController extends Controller
{
    public function __construct(private AbTestingService $service)
    {
    }

    public function index(Request $request)
    {
        $experiments = AbExperiment::where('tenant_id', $request->user->tenant_id)
            ->withCount('variants', 'assignments')
            ->latest()
            ->get();

        return response()->json(['data' => $experiments]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'hypothesis' => 'nullable|string',
            'subject_type' => ['nullable', Rule::in(['sales_script', 'email_template', 'follow_up_sequence'])],
        ]);

        $experiment = AbExperiment::create([
            'tenant_id' => $request->user->tenant_id,
            'status' => 'draft',
            'subject_type' => $validated['subject_type'] ?? 'sales_script',
            ...$validated,
        ]);

        return response()->json(['data' => $experiment], 201);
    }

    public function show(Request $request, $id)
    {
        $experiment = AbExperiment::where('tenant_id', $request->user->tenant_id)
            ->with('variants')
            ->findOrFail($id);

        return response()->json(['data' => $experiment]);
    }

    public function addVariant(Request $request, $id)
    {
        $experiment = AbExperiment::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'label' => 'required|string|max:16',
            'content' => 'required|string',
            'weight' => 'nullable|integer|min:1',
        ]);

        $variant = AbVariant::updateOrCreate(
            ['ab_experiment_id' => $experiment->id, 'label' => $validated['label']],
            ['content' => $validated['content'], 'weight' => $validated['weight'] ?? 1, 'is_active' => true],
        );

        return response()->json(['data' => $variant], 201);
    }

    public function start(Request $request, $id)
    {
        $experiment = AbExperiment::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        // An experiment with one variant is not a test.
        if ($experiment->variants()->where('is_active', true)->count() < 2) {
            return response()->json(['error' => 'Add at least two active variants before starting.'], 422);
        }

        $experiment->update(['status' => 'running', 'started_at' => now()]);

        return response()->json(['data' => $experiment]);
    }

    public function stop(Request $request, $id)
    {
        $experiment = AbExperiment::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $experiment->update(['status' => 'stopped', 'stopped_at' => now()]);

        return response()->json(['data' => $experiment]);
    }

    public function assign(Request $request, $id)
    {
        $experiment = AbExperiment::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate(['lead_id' => 'required|exists:leads,id']);
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($validated['lead_id']);

        $assignment = $this->service->assign($experiment, $lead, $request->user->id);

        if (!$assignment) {
            return response()->json([
                'error' => 'Could not assign: the experiment is not running or has no active variants.',
            ], 422);
        }

        return response()->json(['data' => $assignment->load('variant')]);
    }

    public function recordResponse(Request $request, $assignmentId)
    {
        $assignment = AbAssignment::where('tenant_id', $request->user->tenant_id)->findOrFail($assignmentId);

        return response()->json(['data' => $this->service->recordResponse($assignment)]);
    }

    public function recordConversion(Request $request, $assignmentId)
    {
        $assignment = AbAssignment::where('tenant_id', $request->user->tenant_id)->findOrFail($assignmentId);
        $validated = $request->validate(['booking_value' => 'nullable|numeric|min:0']);

        return response()->json([
            'data' => $this->service->recordConversion($assignment, $validated['booking_value'] ?? null),
        ]);
    }

    public function results(Request $request, $id)
    {
        $experiment = AbExperiment::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        return response()->json(['data' => $this->service->results($experiment)]);
    }
}
