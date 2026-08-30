<?php
namespace App\Services\Ai;

use App\Models\Communication;
use App\Models\CustomerMemory;
use App\Models\Lead;
use App\Models\SalesStrategy;

// AI Sales Copilot (Directive S13) driven by the tenant's configured sales
// strategy (S8) and the customer's structured memory (S9).
//
// Same two guarantees as the Sales Agent, for the same structural reasons:
//   - The model only ever sees real rows (lead, real communications,
//     non-sensitive memory entries) — never invented history.
//   - Everything returned is a SUGGESTION for the human rep. The copilot
//     cannot send, book, price, or change any record.
class AiCopilotService
{
    public function __construct(private AiGateway $gateway)
    {
    }

    /**
     * Live assistance during a customer interaction.
     */
    public function assist(Lead $lead, ?string $latestCustomerMessage = null, ?int $userId = null): array
    {
        $strategy = $this->activeStrategyFor($lead);
        $memory = $this->memoryFor($lead);
        $recent = $this->recentMessages($lead);

        $system = $this->buildSystemPrompt($strategy);

        $payload = [
            'lead' => [
                'name' => $lead->name,
                'status' => $lead->status,
                'source' => $lead->source,
                'priority' => $lead->priority,
                'estimated_value' => $lead->estimated_value,
                'last_contacted_at' => optional($lead->last_contacted_at)->toIso8601String(),
            ],
            'known_customer_memory' => $memory,
            'recent_messages' => $recent,
            'latest_customer_message' => $latestCustomerMessage,
        ];

        $result = $this->gateway->complete(
            $lead->tenant_id,
            'copilot.assist',
            [['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]],
            $system,
            ['max_tokens' => 2000],
            $userId,
        );

        return $this->decodeJson($result->text) + [
            'strategy_used' => $strategy?->key ?? 'none_configured',
            'is_suggestion' => true,
            'human_in_control' => true,
        ];
    }

    /**
     * Extract structured memory candidates from a conversation.
     * Returns CANDIDATES for a human to confirm — nothing is written to
     * customer_memories automatically, because S9 requires memory to be
     * permission-controlled and auditable.
     */
    public function extractMemoryCandidates(Lead $lead, ?int $userId = null): array
    {
        $recent = $this->recentMessages($lead);

        if (empty($recent)) {
            return ['candidates' => [], 'message' => 'No recorded messages to extract memory from.'];
        }

        $categories = implode('", "', CustomerMemory::CATEGORIES);

        $system = <<<PROMPT
Extract durable facts about this customer from the conversation, for a
travel CRM's customer memory.

Return ONLY a JSON object, no prose and no code fences:
{"candidates": [{"category": one of ["{$categories}"],
                 "key": string, "value": string,
                 "confidence": number between 0 and 1,
                 "evidence": string,
                 "possibly_sensitive": boolean}]}

Rules:
- Extract only what the customer actually stated. Never infer a budget,
  a date, or a preference that was not said.
- "evidence" must quote the part of the conversation it came from.
- Mark anything personal beyond travel preferences (health, religion,
  finances, identity documents) as possibly_sensitive so a human can
  decide whether to store it at all.
- Return an empty candidates array if nothing durable was stated.
PROMPT;

        $result = $this->gateway->complete(
            $lead->tenant_id,
            'copilot.extract_memory',
            [['role' => 'user', 'content' => json_encode(['messages' => $recent], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]],
            $system,
            ['max_tokens' => 1500],
            $userId,
        );

        return $this->decodeJson($result->text) + [
            'requires_human_confirmation' => true,
            'stored' => false,
        ];
    }

    /**
     * Highest-priority active strategy, preferring one bound to the lead's
     * customer segment when the lead is linked to a customer in it.
     */
    private function activeStrategyFor(Lead $lead): ?SalesStrategy
    {
        return SalesStrategy::where('tenant_id', $lead->tenant_id)
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN customer_segment_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('priority')
            ->orderBy('id')
            ->first();
    }

    private function buildSystemPrompt(?SalesStrategy $strategy): string
    {
        $methodology = $strategy
            ? "Sales methodology to follow ({$strategy->name}, tone: {$strategy->tone}):\n{$strategy->prompt_guidance}"
            : 'No specific sales methodology is configured; use a straightforward consultative approach.';

        return <<<PROMPT
You are a real-time copilot for a human travel-sales representative.

{$methodology}

Return ONLY a JSON object, no prose and no code fences:
{"suggested_next_question": string,
 "objection_handling": [{"objection": string, "suggested_response": string}],
 "recommended_products": [string],
 "upsell_opportunities": [string],
 "suggested_follow_up_timing": string,
 "customer_sentiment": "positive"|"neutral"|"negative",
 "context_notes": string,
 "facts_to_verify": [string]}

Absolute rules:
- NEVER state a price, flight or hotel availability, a booking
  confirmation, or a visa rule. You do not have that data. Where a
  suggestion depends on such a fact, phrase it as something the rep must
  look up and list it in "facts_to_verify".
- "recommended_products" are categories or package types to discuss, not
  specific inventory you have not been shown.
- Everything you return is a suggestion for the human rep, who decides
  what to say.
PROMPT;
    }

    /**
     * Non-sensitive memory only — sensitive entries are never sent to the
     * model (Directive S9: "Do not store sensitive information
     * unnecessarily"; by extension, do not transmit it unnecessarily).
     */
    private function memoryFor(Lead $lead): array
    {
        return CustomerMemory::where('tenant_id', $lead->tenant_id)
            ->safeForAi()
            ->where(function ($q) use ($lead) {
                $q->where('lead_id', $lead->id);
                if ($lead->customer_id) {
                    $q->orWhere('customer_id', $lead->customer_id);
                }
            })
            ->get(['category', 'key', 'value', 'source', 'confidence'])
            ->map(fn ($m) => [
                'category' => $m->category,
                'key' => $m->key,
                'value' => $m->value,
                'source' => $m->source,
            ])->all();
    }

    private function recentMessages(Lead $lead): array
    {
        if (!$lead->customer_id) {
            return [];
        }

        return Communication::where('tenant_id', $lead->tenant_id)
            ->where('customer_id', $lead->customer_id)
            ->latest()
            ->limit(25)
            ->get(['type', 'subject', 'message', 'sent_at'])
            ->map(fn ($c) => [
                'channel' => $c->type,
                'subject' => $c->subject,
                'message' => $c->message,
                'at' => optional($c->sent_at)->toIso8601String(),
            ])->all();
    }

    private function decodeJson(string $text): array
    {
        $decoded = json_decode(trim($text), true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new AiProviderException('The AI response was not valid JSON: ' . mb_strcut($text, 0, 500));
    }
}
