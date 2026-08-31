<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\Reminder;
use App\Models\Task;
use Illuminate\Http\Request;

class CrmDashboardController extends Controller
{
    private const LEAD_FUNNEL_STAGES = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won'];

    public function index(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $leads = Lead::where('tenant_id', $tenantId)->get();
        $newLeadsThisMonth = Lead::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $totalLeads = $leads->count();
        $wonLeads = $leads->where('status', 'won')->count();
        $conversionRate = $totalLeads > 0 ? round(($wonLeads / $totalLeads) * 100, 2) : 0;

        $pipelineValueByStage = Deal::where('tenant_id', $tenantId)
            ->whereNotIn('stage', ['won', 'lost'])
            ->selectRaw('stage, SUM(amount) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $dealsWon = Deal::where('tenant_id', $tenantId)->where('stage', 'won')->count();
        $dealsLost = Deal::where('tenant_id', $tenantId)->where('stage', 'lost')->count();

        $tasksDueToday = Task::where('tenant_id', $tenantId)
            ->whereDate('due_date', now()->toDateString())
            ->whereNull('completed_at')
            ->count();

        $upcomingFollowUps = Reminder::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('remind_at', '<=', now()->addDays(7))
            ->orderBy('remind_at')
            ->limit(10)
            ->get(['id', 'title', 'remind_at', 'remindable_type', 'remindable_id']);

        return response()->json([
            'data' => [
                'total_leads' => $totalLeads,
                'new_leads_this_month' => $newLeadsThisMonth,
                'conversion_rate' => $conversionRate,
                'pipeline_value_by_stage' => $pipelineValueByStage,
                'deals_won' => $dealsWon,
                'deals_lost' => $dealsLost,
                'tasks_due_today' => $tasksDueToday,
                'upcoming_follow_ups' => $upcomingFollowUps,
            ],
        ]);
    }

    public function conversionFunnel(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $counts = Lead::where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $stages = [];
        foreach (self::LEAD_FUNNEL_STAGES as $stage) {
            $stages[] = [
                'status' => $stage,
                'count' => (int) ($counts[$stage] ?? 0),
            ];
        }

        $totalLeads = $counts->sum();
        $won = (int) ($counts['won'] ?? 0);
        $conversionRate = $totalLeads > 0 ? round(($won / $totalLeads) * 100, 2) : 0;

        return response()->json([
            'data' => [
                'stages' => $stages,
                'total_leads' => $totalLeads,
                'won' => $won,
                'conversion_rate' => $conversionRate,
            ],
        ]);
    }

    public function followUpCalendar(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $from = $request->from ? \Carbon\Carbon::parse($request->from)->startOfDay() : now()->startOfDay();
        $to = $request->to ? \Carbon\Carbon::parse($request->to)->endOfDay() : now()->addDays(30)->endOfDay();

        $events = collect();

        foreach (Reminder::where('tenant_id', $tenantId)
            ->whereBetween('remind_at', [$from, $to])
            ->get() as $reminder) {
            $events->push([
                'id' => 'reminder-' . $reminder->id,
                'type' => 'reminder',
                'title' => $reminder->title,
                'due_date' => $reminder->remind_at,
                'related_type' => $reminder->remindable_type,
                'related_id' => $reminder->remindable_id,
                'status' => $reminder->status,
            ]);
        }

        foreach (Lead::where('tenant_id', $tenantId)
            ->whereNotNull('follow_up_date')
            ->whereBetween('follow_up_date', [$from, $to])
            ->get() as $lead) {
            $events->push([
                'id' => 'lead-follow-up-' . $lead->id,
                'type' => 'lead_follow_up',
                'title' => "Follow up: {$lead->name}",
                'due_date' => $lead->follow_up_date,
                'related_type' => Lead::class,
                'related_id' => $lead->id,
                'status' => $lead->status,
            ]);
        }

        foreach (Task::where('tenant_id', $tenantId)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from, $to])
            ->get() as $task) {
            $events->push([
                'id' => 'task-' . $task->id,
                'type' => 'task',
                'title' => $task->title,
                'due_date' => $task->due_date,
                'related_type' => Task::class,
                'related_id' => $task->id,
                'status' => $task->completed_at ? 'completed' : 'pending',
            ]);
        }

        return response()->json([
            'data' => $events->sortBy('due_date')->values(),
        ]);
    }
}
