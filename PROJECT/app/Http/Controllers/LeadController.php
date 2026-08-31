<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadScore;
use App\Services\LeadConversionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
        private LeadConversionService $leadConversion,
    ) {
    }

    public function index(Request $request)
    {
        $query = Lead::where('tenant_id', $request->user->tenant_id)
            ->with('assignedTo');

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->priority) {
            $query->where('priority', $request->priority);
        }

        if ($request->source) {
            $query->where('source', $request->source);
        }

        $leads = $query->orderBy('conversion_probability', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $leads->items(),
            'pagination' => [
                'total' => $leads->total(),
                'per_page' => $leads->perPage(),
                'current_page' => $leads->currentPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:100',
            'source' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'notes' => 'nullable|string',
        ]);

        $lead = Lead::create([
            'tenant_id' => $request->user->tenant_id,
            'branch_id' => $request->branch_id,
            'assigned_to' => $request->assigned_to,
            'status' => 'new',
            ...$validated
        ]);

        if ($lead->assigned_to) {
            $this->notifications->send(
                $lead->tenant_id,
                $lead->assigned_to,
                'lead_assigned',
                'New lead assigned to you',
                "Lead \"{$lead->name}\" ({$lead->source}) has been assigned to you.",
                ['lead_id' => $lead->id],
                ['in_app', 'telegram']
            );
        }

        return response()->json([
            'message' => 'Lead created successfully',
            'data' => $lead
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)
            ->with('assignedTo')
            ->findOrFail($id);

        $scores = LeadScore::where('lead_id', $id)->get();
        $totalScore = $scores->sum('score');

        return response()->json([
            'data' => $lead,
            'scores' => $scores,
            'total_score' => $totalScore,
        ]);
    }

    public function update(Request $request, $id)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:100',
            'source' => 'sometimes|string',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $lead->update($validated);

        return response()->json([
            'message' => 'Lead updated successfully',
            'data' => $lead,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $lead->delete();

        return response()->json(['message' => 'Lead deleted successfully']);
    }

    public function updateStatus(Request $request, $id)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:new,contacted,qualified,proposal,negotiation,won,lost',
        ]);

        $lead->update($validated);
        $this->leadConversion->convertIfWon($lead, $validated['status']);

        return response()->json([
            'message' => 'Lead status updated',
            'data' => $lead->fresh()
        ]);
    }

    public function assignLead(Request $request, $id)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $lead->update($validated);

        $this->notifications->send(
            $lead->tenant_id,
            $lead->assigned_to,
            'lead_assigned',
            'Lead assigned to you',
            "Lead \"{$lead->name}\" has been assigned to you.",
            ['lead_id' => $lead->id],
            ['in_app', 'telegram']
        );

        return response()->json([
            'message' => 'Lead assigned successfully',
            'data' => $lead
        ]);
    }

    public function scoreLeadForConversion(Request $request, $id)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'factors' => 'required|array',
        ]);

        $scores = [];
        foreach ($validated['factors'] as $factor => $value) {
            LeadScore::create([
                'tenant_id' => $request->user->tenant_id,
                'lead_id' => $id,
                'score_type' => $factor,
                'score' => $value,
            ]);
            $scores[] = $value;
        }

        $totalScore = array_sum($scores);
        $probability = min(100, ($totalScore / (count($scores) * 100)) * 100);

        $lead->update(['conversion_probability' => (int)$probability]);

        return response()->json([
            'message' => 'Lead scored for conversion',
            'conversion_probability' => $probability,
            'data' => $lead
        ]);
    }

    public function scheduleFollowUp(Request $request, $id)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'follow_up_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $lead->update([
            'follow_up_date' => $validated['follow_up_date'],
            'notes' => $validated['notes'] ?? $lead->notes,
        ]);

        return response()->json([
            'message' => 'Follow-up scheduled',
            'data' => $lead
        ]);
    }

    public function pendingFollowUps(Request $request)
    {
        $leads = Lead::where('tenant_id', $request->user->tenant_id)
            ->whereNotNull('follow_up_date')
            ->where('follow_up_date', '<=', now()->addDays(3))
            ->where('status', '!=', 'won')
            ->orderBy('follow_up_date', 'asc')
            ->get();

        return response()->json([
            'data' => $leads,
            'count' => $leads->count()
        ]);
    }
}
