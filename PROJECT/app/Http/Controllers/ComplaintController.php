<?php
namespace App\Http\Controllers;
use App\Models\Complaint;
use App\Services\ComplaintService;
use App\Services\AI\AIOrchestrator;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function __construct(protected ComplaintService $complaints, protected AIOrchestrator $orchestrator)
    {
    }

    public function index(Request $request)
    {
        $query = Complaint::where('tenant_id', $request->user->tenant_id)->with('assignedStaff:id,name');
        if ($request->status) $query->where('status', $request->status);
        if ($request->priority) $query->where('priority', $request->priority);
        return response()->json(['data' => $query->orderBy('created_at', 'desc')->paginate(20)->items()]);
    }

    public function show(Request $request, $id)
    {
        $complaint = Complaint::where('tenant_id', $request->user->tenant_id)
            ->with('booking', 'customer', 'assignedStaff')
            ->findOrFail($id);
        return response()->json(['data' => $complaint]);
    }

    /**
     * FIX (Phase 14 audit): now runs real (deterministic) classification
     * and sets a real SLA deadline — previously this required category/
     * priority to be supplied manually with no SLA at all.
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'customer_id' => 'required|exists:customers,id',
            'title' => 'required|string',
            'description' => 'required|string',
        ]);

        $classification = $this->complaints->classify($validated['title'], $validated['description']);

        $complaint = Complaint::create([
            'tenant_id' => $request->user->tenant_id,
            'status' => 'open',
            'category' => $classification['category'],
            'priority' => $classification['priority'],
            'involves_compensation' => $classification['involves_compensation'],
            'approval_status' => $classification['involves_compensation'] ? 'pending' : 'not_required',
            'sla_deadline' => $this->complaints->calculateSlaDeadline($classification['priority']),
            ...$validated,
        ]);

        return response()->json(['data' => $complaint], 201);
    }

    /**
     * FIX (Phase 14 audit): this previously called
     * $complaint->update(['status' => $request->status]) with NO
     * validation at all — any arbitrary string could be written as the
     * status.
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate(['status' => 'required|in:open,in_progress,escalated,resolved,closed']);

        $complaint = Complaint::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $complaint->update(['status' => $validated['status']]);
        if ($validated['status'] === 'resolved') {
            $complaint->update(['resolution_date' => now()]);
        }
        return response()->json(['data' => $complaint]);
    }

    public function assign(Request $request, $id)
    {
        $validated = $request->validate(['user_id' => 'required|exists:users,id']);
        $complaint = Complaint::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $complaint->update(['assigned_to' => $validated['user_id'], 'status' => 'in_progress']);
        return response()->json(['data' => $complaint->load('assignedStaff')]);
    }

    public function resolve(Request $request, $id)
    {
        $validated = $request->validate(['resolution' => 'required|string']);
        try {
            $complaint = $this->complaints->resolveComplaint($id, $validated['resolution'], $request->user->tenant_id);
            return response()->json(['data' => $complaint]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * The ONLY path to a real refund being created for a complaint —
     * requires an explicit, authenticated human action.
     */
    public function approveCompensation(Request $request, $id)
    {
        $validated = $request->validate(['amount' => 'required|numeric|min:0']);
        $complaint = Complaint::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if (!$complaint->involves_compensation) {
            return response()->json(['error' => 'This complaint was not flagged as involving compensation'], 400);
        }

        $complaint = $this->complaints->approveCompensation($complaint, $validated['amount'], $request->user->id);
        return response()->json(['data' => $complaint]);
    }

    public function rejectCompensation(Request $request, $id)
    {
        $complaint = Complaint::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        return response()->json(['data' => $this->complaints->rejectCompensation($complaint)]);
    }

    /**
     * Staff/cron-triggered SLA breach sweep — same honesty pattern as
     * Phase 7's reminder sweeps (real cron execution unverified here).
     */
    public function checkSlaBreaches(Request $request)
    {
        $escalated = $this->complaints->checkAndEscalateBreaches($request->user->tenant_id);
        return response()->json(['message' => count($escalated) . ' complaint(s) escalated for SLA breach', 'data' => $escalated]);
    }

    /**
     * AI-dependent suggested response. Explicitly does NOT let the AI
     * promise compensation — the prompt instructs it to defer money
     * questions to a human, and even if it ignored that, no code path
     * here ever creates a Payment from this response; only
     * approveCompensation() (a separate, human-only action) can.
     */
    public function suggestResponse(Request $request, $id)
    {
        $complaint = Complaint::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $prompt = "A customer filed this complaint (category: {$complaint->category}, priority: {$complaint->priority}): \"{$complaint->description}\". "
            . "Suggest a short, empathetic reply a support agent could send. Do NOT promise any refund, compensation, or specific monetary outcome — "
            . "if compensation seems warranted, say a team member will review and follow up on that separately.";

        $result = $this->orchestrator->complete($request->user->tenant_id, 'complaint_response', $prompt, ['max_tokens' => 300]);

        return response()->json(['data' => [
            'suggestion' => $result['success'] ? $result['text'] : null,
            'status' => $result['status'],
            'error' => $result['error'] ?? null,
        ]]);
    }
}
