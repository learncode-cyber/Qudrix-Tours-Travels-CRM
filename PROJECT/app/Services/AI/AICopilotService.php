<?php

namespace App\Services\AI;

use App\Models\Lead;
use App\Models\Conversation;
use App\Models\Quotation;
use App\Models\Proposal;
use App\Models\SalesScript;
use App\Models\ObjectionResponse;
use App\Models\SalesStrategyConfig;

class AICopilotService
{
    public function __construct(protected AIOrchestrator $orchestrator)
    {
    }

    /**
     * PHASE 11: Deal risk detection — deliberately built as deterministic
     * rule-based logic, NOT an AI call. Real signals (staleness, overdue
     * follow-ups, expiring quotations) give a genuinely useful risk
     * signal without depending on an AI provider being configured.
     */
    public function dealRiskScore(Lead $lead): array
    {
        $signals = [];
        $riskPoints = 0;

        $daysSinceContact = $lead->last_contacted_at
            ? now()->diffInDays($lead->last_contacted_at)
            : ($lead->created_at ? now()->diffInDays($lead->created_at) : null);

        if ($daysSinceContact !== null && $daysSinceContact > 14) {
            $signals[] = "No contact in {$daysSinceContact} days";
            $riskPoints += min(40, $daysSinceContact);
        }

        if ($lead->follow_up_date && $lead->follow_up_date->isPast()) {
            $daysOverdue = now()->diffInDays($lead->follow_up_date);
            $signals[] = "Follow-up overdue by {$daysOverdue} days";
            $riskPoints += min(30, $daysOverdue * 2);
        }

        $expiredQuotation = Quotation::where('lead_id', $lead->id)
            ->where('status', 'sent')
            ->where('valid_until', '<', now())
            ->exists();

        if ($expiredQuotation) {
            $signals[] = 'Sent quotation has expired without a response';
            $riskPoints += 25;
        }

        $unsignedProposal = Proposal::where('lead_id', $lead->id)
            ->where('status', 'sent')
            ->where('expiry_date', '<', now())
            ->exists();

        if ($unsignedProposal) {
            $signals[] = 'Sent proposal has expired without being signed';
            $riskPoints += 25;
        }

        if ($lead->conversion_probability !== null && $lead->conversion_probability < 30) {
            $signals[] = "Low stated conversion probability ({$lead->conversion_probability}%)";
            $riskPoints += 10;
        }

        $riskPoints = min(100, $riskPoints);
        $level = $riskPoints >= 60 ? 'high' : ($riskPoints >= 30 ? 'medium' : 'low');

        return [
            'lead_id' => $lead->id,
            'risk_score' => $riskPoints,
            'risk_level' => $level,
            'signals' => $signals ?: ['No risk signals detected'],
        ];
    }

    /**
     * PHASE 11: Next-best-action — also deterministic. Checks the real
     * sales-chain state (Phase 8) rather than asking AI to guess it.
     */
    public function nextBestAction(Lead $lead): array
    {
        if ($lead->status === 'won' || $lead->status === 'lost') {
            return ['action' => 'none', 'reason' => "Deal already {$lead->status}"];
        }

        $pendingQuotation = Quotation::where('lead_id', $lead->id)
            ->where('status', 'sent')
            ->where(function ($q) { $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()); })
            ->latest()
            ->first();

        if ($pendingQuotation) {
            return [
                'action' => 'follow_up_on_quotation',
                'reason' => "Quotation {$pendingQuotation->quotation_number} sent, awaiting response",
                'reference' => ['type' => 'quotation', 'id' => $pendingQuotation->id],
            ];
        }

        $pendingProposal = Proposal::where('lead_id', $lead->id)->where('status', 'sent')->latest()->first();
        if ($pendingProposal) {
            return [
                'action' => 'follow_up_on_proposal',
                'reason' => "Proposal {$pendingProposal->proposal_number} sent, awaiting signature",
                'reference' => ['type' => 'proposal', 'id' => $pendingProposal->id],
            ];
        }

        if ($lead->follow_up_date && $lead->follow_up_date->isToday()) {
            return ['action' => 'follow_up_due_today', 'reason' => 'A follow-up was scheduled for today'];
        }

        if ($lead->follow_up_date && $lead->follow_up_date->isPast()) {
            return ['action' => 'overdue_follow_up', 'reason' => 'Scheduled follow-up date has passed'];
        }

        if (!$lead->last_contacted_at) {
            return ['action' => 'make_first_contact', 'reason' => 'No contact has been logged yet'];
        }

        return ['action' => 'send_quotation', 'reason' => 'No active quotation or proposal — consider sending one'];
    }

    /**
     * AI-dependent: reply suggestions for a live conversation, drawing
     * on the tenant's configured strategy and script/objection library
     * as real inputs, same pattern as Phase 10's inventory injection.
     */
    public function suggestReply(Conversation $conversation, string $latestCustomerMessage): array
    {
        $tenantId = $conversation->tenant_id;
        $strategy = SalesStrategyConfig::where('tenant_id', $tenantId)->value('strategy') ?? 'consultative';

        $scripts = SalesScript::where('tenant_id', $tenantId)->where('strategy', $strategy)->limit(5)->get();
        $objections = ObjectionResponse::where('tenant_id', $tenantId)->limit(10)->get();

        $scriptText = $scripts->isEmpty() ? 'None configured.' : $scripts->map(fn ($s) => "- [{$s->category}] {$s->title}: {$s->content}")->implode("\n");
        $objectionText = $objections->isEmpty() ? 'None configured.' : $objections->map(fn ($o) => "- If customer says \"{$o->objection}\": {$o->suggested_response}")->implode("\n");

        $prompt = <<<PROMPT
You are a sales coaching assistant helping a human salesperson respond to a customer. Strategy: {$strategy} selling.

RELEVANT SCRIPTS:
{$scriptText}

OBJECTION HANDLING GUIDE:
{$objectionText}

CUSTOMER'S LATEST MESSAGE: {$latestCustomerMessage}

Suggest a reply the salesperson could send (2-3 sentences), and briefly note which technique you're applying.
PROMPT;

        $result = $this->orchestrator->complete($tenantId, 'sales_copilot', $prompt, ['max_tokens' => 300]);

        return [
            'suggestion' => $result['success'] ? $result['text'] : null,
            'status' => $result['status'],
            'error' => $result['error'] ?? null,
        ];
    }
}
