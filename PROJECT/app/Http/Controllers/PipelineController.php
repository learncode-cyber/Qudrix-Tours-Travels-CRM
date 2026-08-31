<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\DealStage;
use App\Models\SalesActivity;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function getFullPipeline(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $stages = [
            'new' => [],
            'contacted' => [],
            'qualified' => [],
            'proposal' => [],
            'negotiation' => [],
            'won' => [],
            'lost' => []
        ];

        foreach ($stages as $status => $data) {
            $leads = Lead::where('tenant_id', $tenantId)
                ->where('status', $status)
                ->with('assignedTo')
                ->get();

            $stages[$status] = [
                'count' => $leads->count(),
                'total_value' => $leads->sum('estimated_value'),
                'leads' => $leads,
            ];
        }

        return response()->json([
            'data' => $stages,
            'pipeline_value' => array_reduce($stages, function($carry, $stage) {
                return $carry + $stage['total_value'];
            }, 0)
        ]);
    }

    public function getLeadPipeline(Request $request, $leadId)
    {
        $lead = Lead::where('tenant_id', $request->user->tenant_id)
            ->findOrFail($leadId);

        $stages = DealStage::where('lead_id', $leadId)
            ->orderBy('entered_at', 'desc')
            ->get();

        $activities = SalesActivity::where('lead_id', $leadId)
            ->orderBy('activity_date', 'desc')
            ->get();

        return response()->json([
            'lead' => $lead,
            'stages' => $stages,
            'activities' => $activities,
        ]);
    }

    public function recordActivity(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'activity_type' => 'required|in:call,email,meeting,note,proposal_sent,quote_sent',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'outcome' => 'nullable|in:positive,neutral,negative',
            'activity_date' => 'required|date',
        ]);

        $activity = SalesActivity::create([
            'tenant_id' => $request->user->tenant_id,
            'user_id' => $request->user->id,
            ...$validated
        ]);

        return response()->json([
            'message' => 'Activity recorded',
            'data' => $activity
        ], 201);
    }

    public function updateLeadStage(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'new_stage' => 'required|in:new,contacted,qualified,proposal,negotiation,won,lost',
        ]);

        $lead = Lead::where('tenant_id', $request->user->tenant_id)
            ->findOrFail($validated['lead_id']);

        // Close current stage
        $currentStage = DealStage::where('lead_id', $lead->id)
            ->whereNull('exited_at')
            ->first();

        if ($currentStage) {
            $currentStage->update([
                'exited_at' => now(),
                'duration_days' => $currentStage->calculateDuration()
            ]);
        }

        // Create new stage
        DealStage::create([
            'tenant_id' => $request->user->tenant_id,
            'lead_id' => $lead->id,
            'stage' => $validated['new_stage'],
            'entered_at' => now(),
        ]);

        // Update lead status
        $lead->update(['status' => $validated['new_stage']]);

        return response()->json([
            'message' => 'Lead stage updated',
            'data' => $lead
        ]);
    }

    public function salesActivityHistory(Request $request)
    {
        $query = SalesActivity::where('tenant_id', $request->user->tenant_id)
            ->with(['lead', 'user']);

        if ($request->lead_id) {
            $query->where('lead_id', $request->lead_id);
        }

        if ($request->activity_type) {
            $query->where('activity_type', $request->activity_type);
        }

        $activities = $query->orderBy('activity_date', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $activities->items(),
            'pagination' => [
                'total' => $activities->total(),
                'per_page' => $activities->perPage(),
                'current_page' => $activities->currentPage(),
            ],
        ]);
    }

    public function getPipelineMetrics(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $leads = Lead::where('tenant_id', $tenantId)->get();

        $metrics = [
            'total_leads' => $leads->count(),
            'total_pipeline_value' => $leads->sum('estimated_value'),
            'average_deal_size' => $leads->avg('estimated_value'),
            'by_status' => Lead::where('tenant_id', $tenantId)
                ->selectRaw('status, count(*) as count, sum(estimated_value) as value')
                ->groupBy('status')
                ->pluck('value', 'status')
                ->toArray(),
            'by_priority' => Lead::where('tenant_id', $tenantId)
                ->selectRaw('priority, count(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray(),
            'win_rate' => $leads->count() > 0 
                ? round(($leads->where('status', 'won')->count() / $leads->count()) * 100, 2)
                : 0,
        ];

        return response()->json(['data' => $metrics]);
    }
}
