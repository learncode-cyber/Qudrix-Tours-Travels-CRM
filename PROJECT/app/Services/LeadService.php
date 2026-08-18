<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadScore;

class LeadService
{
    public function scoreLeadForConversion(int $leadId, array $factors): int
    {
        $scores = [];
        foreach ($factors as $type => $value) {
            LeadScore::create([
                'lead_id' => $leadId,
                'score_type' => $type,
                'score' => min(100, max(0, $value)),
            ]);
            $scores[] = $value;
        }

        $totalScore = array_sum($scores);
        $probability = count($scores) > 0 
            ? (int)(($totalScore / (count($scores) * 100)) * 100)
            : 0;

        Lead::where('id', $leadId)->update([
            'conversion_probability' => $probability
        ]);

        return $probability;
    }

    public function getLeadPipeline(int $tenantId)
    {
        $statuses = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];
        $pipeline = [];

        foreach ($statuses as $status) {
            $leads = Lead::where('tenant_id', $tenantId)
                ->where('status', $status)
                ->get();

            $pipeline[$status] = [
                'count' => $leads->count(),
                'value' => $leads->sum('estimated_value'),
                'leads' => $leads,
            ];
        }

        return $pipeline;
    }

    public function getPendingFollowUps(int $tenantId)
    {
        return Lead::where('tenant_id', $tenantId)
            ->whereNotNull('follow_up_date')
            ->where('follow_up_date', '<=', now()->addDays(3))
            ->where('status', '!=', 'won')
            ->orderBy('follow_up_date')
            ->get();
    }

    public function convertLeadToCustomer(int $leadId)
    {
        $lead = Lead::findOrFail($leadId);
        
        $customer = Customer::create([
            'tenant_id' => $lead->tenant_id,
            'branch_id' => $lead->branch_id,
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'customer_type' => 'individual',
            'status' => 'active',
        ]);

        $lead->update(['status' => 'won']);

        return $customer;
    }

    public function getLeadMetrics(int $tenantId)
    {
        return [
            'total_leads' => Lead::where('tenant_id', $tenantId)->count(),
            'new_leads_today' => Lead::where('tenant_id', $tenantId)
                ->whereDate('created_at', today())
                ->count(),
            'conversion_rate' => $this->calculateConversionRate($tenantId),
            'average_score' => Lead::where('tenant_id', $tenantId)
                ->average('conversion_probability'),
            'by_source' => Lead::where('tenant_id', $tenantId)
                ->selectRaw('source, count(*) as count')
                ->groupBy('source')
                ->pluck('count', 'source'),
            'by_priority' => Lead::where('tenant_id', $tenantId)
                ->selectRaw('priority, count(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority'),
        ];
    }

    private function calculateConversionRate(int $tenantId): float
    {
        $total = Lead::where('tenant_id', $tenantId)->count();
        if ($total === 0) return 0;

        $converted = Lead::where('tenant_id', $tenantId)
            ->where('status', 'won')
            ->count();

        return round(($converted / $total) * 100, 2);
    }
}
