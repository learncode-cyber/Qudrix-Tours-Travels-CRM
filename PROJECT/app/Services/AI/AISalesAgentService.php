<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Package;

/**
 * PHASE 10: AI Sales Agent.
 *
 * Two hard constraints from the phase spec, enforced structurally here
 * rather than just hoped for via prompt wording:
 * 1. "AI must NEVER invent availability, visa approval, hotel
 *    availability, flight availability, or external pricing." — real
 *    Package rows (from the real database, same source Phase 6's
 *    pricing engine uses) are injected into the system prompt as the
 *    ONLY source of package/price information the model is given. It
 *    cannot invent what it was never told.
 * 2. Human handoff must be reliable, not dependent on the model
 *    correctly deciding to hand off — a deterministic keyword trigger
 *    on the customer's message runs BEFORE the AI call, so "talk to a
 *    human" always works even if the AI call itself fails or is
 *    unavailable.
 */
class AISalesAgentService
{
    protected array $handoffPhrases = [
        'talk to a human', 'speak to a human', 'human agent', 'real person',
        'speak to someone', 'talk to someone', 'customer service', 'human support',
    ];

    public function __construct(protected AIOrchestrator $orchestrator)
    {
    }

    public function sendMessage(Conversation $conversation, string $customerMessage): array
    {
        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'customer',
            'content' => $customerMessage,
        ]);

        if ($this->requestsHumanHandoff($customerMessage)) {
            $this->escalate($conversation, 'Customer explicitly requested a human agent.');

            return [
                'reply' => "Of course - I'm connecting you with a member of our team who will be with you shortly.",
                'escalated' => true,
                'ai_status' => 'not_called',
            ];
        }

        $history = $conversation->messages()->get();
        $prompt = $this->buildPrompt($conversation, $history, $customerMessage);

        $result = $this->orchestrator->complete($conversation->tenant_id, 'sales_agent', $prompt, ['max_tokens' => 500]);

        if (!$result['success']) {
            $this->escalate($conversation, 'AI provider unavailable: ' . ($result['error'] ?? 'unknown error'));

            return [
                'reply' => "I'm having trouble responding right now - let me get a team member to help you.",
                'escalated' => true,
                'ai_status' => $result['status'],
            ];
        }

        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'agent',
            'content' => $result['text'],
            'metadata' => ['provider' => $result['provider'] ?? null, 'model' => $result['model'] ?? null],
        ]);

        return ['reply' => $result['text'], 'escalated' => false, 'ai_status' => 'success'];
    }

    protected function requestsHumanHandoff(string $message): bool
    {
        $lower = strtolower($message);
        foreach ($this->handoffPhrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }
        return false;
    }

    protected function escalate(Conversation $conversation, string $reason): void
    {
        $conversation->update(['status' => 'needs_human', 'escalated_at' => now()]);

        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => "Escalated to human: {$reason}",
        ]);
    }

    protected function buildPrompt(Conversation $conversation, $history, string $latestMessage): string
    {
        $packages = Package::where('tenant_id', $conversation->tenant_id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->limit(20)
            ->get(['name', 'destination', 'days', 'nights', 'base_price', 'currency']);

        $inventoryText = $packages->isEmpty()
            ? 'No active packages are currently available in the system.'
            : $packages->map(fn ($p) => "- {$p->name} ({$p->destination}, {$p->days}d/{$p->nights}n): {$p->base_price} {$p->currency}")->implode("\n");

        $conversationText = $history->map(fn ($m) => strtoupper($m->role) . ': ' . $m->content)->implode("\n");

        return <<<PROMPT
You are a professional, consultative travel sales assistant. Use consultative selling: understand the customer's needs before recommending anything. Ask clarifying questions when requirements are unclear (destination, dates, group size, budget, preferences).

STRICT RULES - do not break these:
- Only recommend packages from the list below. Never invent a package, price, or availability that is not listed.
- Never guarantee visa approval or promise outcomes outside the company's control.
- If you don't have enough information to help, ask a clarifying question rather than guessing.
- Be honest if something is outside what you can help with, and suggest the customer can ask for a human agent.

AVAILABLE PACKAGES (the only ones you may reference):
{$inventoryText}

CONVERSATION SO FAR:
{$conversationText}

CUSTOMER: {$latestMessage}

Respond as the sales assistant, in 2-4 sentences.
PROMPT;
    }
}
