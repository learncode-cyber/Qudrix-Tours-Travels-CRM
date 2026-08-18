<?php

namespace App\Services;

use App\Models\Proposal;
use App\Models\Quotation;

class ProposalService
{
    public function createProposalFromQuotation(int $tenantId, Quotation $quotation, array $data)
    {
        $proposal = Proposal::create([
            'tenant_id' => $tenantId,
            'quotation_id' => $quotation->id,
            'lead_id' => $quotation->lead_id,
            'customer_id' => $quotation->customer_id,
            'proposal_number' => 'PROP-' . time(),
            'status' => 'draft',
            'proposal_date' => now(),
            ...$data
        ]);

        return $proposal;
    }

    public function getProposalConversionRate(int $tenantId)
    {
        $total = Proposal::where('tenant_id', $tenantId)->count();
        if ($total === 0) return 0;

        $signed = Proposal::where('tenant_id', $tenantId)
            ->where('status', 'signed')
            ->count();

        return round(($signed / $total) * 100, 2);
    }

    public function getAverageTimeToSign(int $tenantId)
    {
        $signed = Proposal::where('tenant_id', $tenantId)
            ->where('status', 'signed')
            ->whereNotNull('sent_date')
            ->whereNotNull('signed_date')
            ->get();

        if ($signed->isEmpty()) return 0;

        $totalDays = 0;
        foreach ($signed as $proposal) {
            $totalDays += $proposal->sent_date->diffInDays($proposal->signed_date);
        }

        return round($totalDays / $signed->count(), 2);
    }
}
