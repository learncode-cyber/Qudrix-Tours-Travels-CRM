<?php
namespace App\Services\Ai;

use App\Models\Booking;
use App\Models\Communication;
use App\Models\Lead;
use App\Models\LeadScore;
use App\Models\Quotation;

// AI Sales Agent (Directive S5) and AI-assisted lead scoring (S10).
//
// Two hard rules, both enforced here rather than by prompt wording alone:
//
//  1. GROUNDING — the model is only ever shown real rows from this tenant
//     (the lead, its real communications, real quotations, real bookings).
//     It is never asked about availability, prices, or visa rules, because
//     those are facts the system owns and the model would otherwise guess.
//
//  2. SUGGESTIONS ONLY — nothing this service returns is applied
//     automatically. Scores are written as an 'ai_suggested' LeadScore row
//     that a human can override (S10), and reply drafts are returned to the
//     rep, never sent (S13: "AI suggestions must remain suggestions").
class AiSalesAgentService
{
    public function __construct(private AiGateway $gateway)
    {
    }

    /**
     * Qualify a lead from its real history. Returns a score, buying-intent
     * read, and a recommended next action — all as suggestions.
     */
    public function qualifyLead(Lead $lead, ?int $userId = null): array
    {
        $context = $this->leadContext($lead);

        $system = <<<'PROMPT'
You are a travel-sales analyst. You will be given a CRM lead's real
record and real interaction history.

Return ONLY a JSON object, no prose and no code fences:
{"score": integer 0-100,
 "buying_intent": "low"|"medium"|"high",
 "reasoning": string,
 "signals": [{"signal": string, "impact": "positive"|"negative"}],
 "recommended_next_action": string,
 "suggested_follow_up_days": integer,
 "objections_detected": [string],
 "missing_information": [string]}

Rules:
- Base every statement strictly on the supplied data. Do not assume facts
  that are not present.
- Never state prices, availability, or visa rules — you do not have that
  information and the system owns it.
- If the history is too thin to judge, say so in "reasoning" and score
  conservatively.
PROMPT;

        $result = $this->gateway->complete(
            $lead->tenant_id,
            'sales_agent.qualify_lead',
            [['role' => 'user', 'content' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]],
            $system,
            ['max_tokens' => 1500],
            $userId,
        );

        $analysis = $this->decodeJson($result->text);

        // Persisted as a suggestion, clearly attributed, never overwriting a
        // human's own scoring. A staff member can override it (Directive S10).
        if (isset($analysis['score']) && is_numeric($analysis['score'])) {
            LeadScore::create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'score' => (int) $analysis['score'],
                'score_type' => 'ai_suggested',
                'description' => $analysis['reasoning'] ?? null,
                'metadata' => [
                    'buying_intent' => $analysis['buying_intent'] ?? null,
                    'signals' => $analysis['signals'] ?? [],
                    'recommended_next_action' => $analysis['recommended_next_action'] ?? null,
                    'generated_at' => now()->toIso8601String(),
                ],
            ]);
        }

        return $analysis + ['is_suggestion' => true, 'human_can_override' => true];
    }

    /**
     * Summarize a lead's conversation history (Directive S5).
     */
    public function summarizeConversation(Lead $lead, ?int $userId = null): array
    {
        $context = $this->leadContext($lead);

        if (empty($context['communications'])) {
            return ['summary' => null, 'message' => 'This lead has no recorded communications to summarize.'];
        }

        $system = <<<'PROMPT'
Summarize this customer conversation for a sales rep picking it up.

Return ONLY a JSON object, no prose and no code fences:
{"summary": string, "customer_requirements": [string],
 "open_questions": [string], "commitments_made": [string],
 "sentiment": "positive"|"neutral"|"negative"}

Base everything strictly on the supplied messages. Do not infer
commitments that were not actually made.
PROMPT;

        $result = $this->gateway->complete(
            $lead->tenant_id,
            'sales_agent.summarize',
            [['role' => 'user', 'content' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]],
            $system,
            ['max_tokens' => 1500],
            $userId,
        );

        return $this->decodeJson($result->text);
    }

    /**
     * Draft a reply for the rep to review, edit, and send themselves.
     * Nothing is sent from here.
     */
    public function suggestReply(Lead $lead, ?string $repIntent = null, ?int $userId = null): array
    {
        $context = $this->leadContext($lead);

        $system = <<<'PROMPT'
You draft a reply for a human travel-sales rep to review and send.

Return ONLY a JSON object, no prose and no code fences:
{"draft": string, "tone": string, "rationale": string,
 "facts_to_verify_before_sending": [string]}

Absolute rules:
- NEVER state a price, a flight or hotel availability, a booking
  confirmation, or a visa rule. You do not have that data. Where the reply
  needs such a fact, write a clear placeholder like [CONFIRM PRICE] and add
  it to "facts_to_verify_before_sending".
- The draft is a suggestion for a human to edit. Do not imply it has been
  sent.
PROMPT;

        $payload = $context + ['rep_intent' => $repIntent];

        $result = $this->gateway->complete(
            $lead->tenant_id,
            'sales_agent.suggest_reply',
            [['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]],
            $system,
            ['max_tokens' => 1500],
            $userId,
        );

        return $this->decodeJson($result->text) + ['is_draft' => true, 'sent' => false];
    }

    /**
     * The only data any prompt in this class sees: real rows, this tenant.
     */
    private function leadContext(Lead $lead): array
    {
        $communications = Communication::where('tenant_id', $lead->tenant_id)
            ->where('customer_id', $lead->customer_id ?? 0)
            ->latest()
            ->limit(30)
            ->get(['type', 'subject', 'message', 'status', 'sent_at'])
            ->map(fn ($c) => [
                'channel' => $c->type,
                'subject' => $c->subject,
                'message' => $c->message,
                'at' => optional($c->sent_at)->toIso8601String(),
            ])->all();

        $quotations = Quotation::where('tenant_id', $lead->tenant_id)
            ->where('lead_id', $lead->id)
            ->get(['quotation_number', 'status', 'total_amount', 'currency', 'valid_until', 'created_at'])
            ->map(fn ($q) => [
                'number' => $q->quotation_number,
                'status' => $q->status,
                'total' => (float) $q->total_amount,
                'currency' => $q->currency,
                'valid_until' => optional($q->valid_until)->toDateString(),
            ])->all();

        $bookings = Booking::where('tenant_id', $lead->tenant_id)
            ->where('lead_id', $lead->id)
            ->get(['booking_number', 'status', 'payment_status', 'travel_date'])
            ->map(fn ($b) => [
                'number' => $b->booking_number,
                'status' => $b->status,
                'payment_status' => $b->payment_status,
                'travel_date' => optional($b->travel_date)->toDateString(),
            ])->all();

        return [
            'lead' => [
                'name' => $lead->name,
                'source' => $lead->source,
                'status' => $lead->status,
                'priority' => $lead->priority,
                'company' => $lead->company,
                'estimated_value' => $lead->estimated_value,
                'notes' => $lead->notes,
                'created_at' => optional($lead->created_at)->toIso8601String(),
                'last_contacted_at' => optional($lead->last_contacted_at)->toIso8601String(),
                'follow_up_date' => optional($lead->follow_up_date)->toIso8601String(),
            ],
            'communications' => $communications,
            'quotations' => $quotations,
            'bookings' => $bookings,
        ];
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
