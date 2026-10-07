<?php
namespace App\Services;
use App\Models\Complaint;
use App\Models\Payment;

/**
 * PHASE 14: extended with real (deterministic) classification, SLA
 * tracking, escalation, and — the phase spec's explicit hard rule —
 * "AI must not promise refunds/compensation unless company rules
 * explicitly allow it". Enforced structurally: a complaint that
 * mentions compensation is flagged 'pending' approval and NO Payment
 * record is ever created until a human explicitly approves it via
 * approveCompensation(). There is no AI call anywhere in this file that
 * could authorize money movement on its own.
 */
class ComplaintService
{
    /**
     * Keyword-based classification — deterministic, not AI. Real
     * language-understanding classification would need an AI call
     * (BLOCKED without credentials, same as every other phase); this
     * covers the common, unambiguous cases without depending on one.
     */
    protected array $categoryKeywords = [
        'billing' => ['refund', 'charged', 'overcharge', 'payment', 'invoice', 'money back'],
        'service_quality' => ['rude', 'unprofessional', 'disrespect', 'poor service'],
        'logistics' => ['delay', 'late', 'missed flight', 'wrong hotel', 'cancelled'],
        'visa' => ['visa rejected', 'visa denied', 'visa delay'],
    ];

    protected array $urgentKeywords = ['legal action', 'lawyer', 'sue', 'emergency', 'stranded'];
    protected array $compensationKeywords = ['refund', 'compensation', 'money back', 'reimburse'];

    protected array $slaHoursByPriority = ['urgent' => 4, 'high' => 24, 'medium' => 48, 'low' => 72];

    public function classify(string $title, string $description): array
    {
        $text = strtolower($title . ' ' . $description);

        $category = 'general';
        foreach ($this->categoryKeywords as $cat => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) {
                    $category = $cat;
                    break 2;
                }
            }
        }

        $priority = 'medium';
        foreach ($this->urgentKeywords as $kw) {
            if (str_contains($text, $kw)) {
                $priority = 'urgent';
                break;
            }
        }

        $involvesCompensation = false;
        foreach ($this->compensationKeywords as $kw) {
            if (str_contains($text, $kw)) {
                $involvesCompensation = true;
                break;
            }
        }

        return ['category' => $category, 'priority' => $priority, 'involves_compensation' => $involvesCompensation];
    }

    public function calculateSlaDeadline(string $priority): \Illuminate\Support\Carbon
    {
        $hours = $this->slaHoursByPriority[$priority] ?? 48;
        return now()->addHours($hours);
    }

    public function checkAndEscalateBreaches(int $tenantId): array
    {
        $breached = Complaint::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '<', now())
            ->where('sla_breached', false)
            ->get();

        foreach ($breached as $complaint) {
            $complaint->update(['sla_breached' => true, 'escalated_at' => now()]);
        }

        return $breached->pluck('id')->toArray();
    }

    /**
     * A human explicitly approving compensation — the only path by
     * which a refund actually gets created. No automatic or AI-driven
     * path exists to this.
     */
    public function approveCompensation(Complaint $complaint, float $amount, int $approvedByUserId): Complaint
    {
        $complaint->update([
            'approval_status' => 'approved',
            'compensation_amount' => $amount,
        ]);

        if ($complaint->booking_id) {
            Payment::create([
                'tenant_id' => $complaint->tenant_id,
                'booking_id' => $complaint->booking_id,
                'amount' => $amount,
                'payment_method' => 'refund',
                'status' => 'refunded',
                'paid_at' => now(),
                'notes' => "Compensation approved for complaint #{$complaint->id} by user #{$approvedByUserId}",
            ]);
        }

        return $complaint->fresh();
    }

    public function rejectCompensation(Complaint $complaint): Complaint
    {
        $complaint->update(['approval_status' => 'rejected']);
        return $complaint->fresh();
    }

    public function resolveComplaint($complaintId, $resolution, ?int $tenantId = null)
    {
        $query = Complaint::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $complaint = $query->findOrFail($complaintId);

        if ($complaint->involves_compensation && $complaint->approval_status !== 'approved') {
            throw new \RuntimeException('This complaint involves compensation that has not yet been approved — resolve the approval first.');
        }

        $complaint->update(['status' => 'resolved', 'resolution' => $resolution, 'resolution_date' => now()]);
        return $complaint;
    }

    public function getStatsByPriority($tenantId)
    {
        return Complaint::where('tenant_id', $tenantId)
            ->groupBy('priority')
            ->selectRaw('priority, count(*) as count')
            ->pluck('count', 'priority');
    }

    public function getAverageResolutionTime($tenantId)
    {
        $resolved = Complaint::where('tenant_id', $tenantId)
            ->whereNotNull('resolution_date')
            ->selectRaw('DATEDIFF(resolution_date, created_at) as days_to_resolve')
            ->get();
        return $resolved->avg('days_to_resolve');
    }
}
