<?php
namespace App\Services\Ai;

use App\Models\SupportTicket;
use App\Models\TicketAiTriage;
use App\Models\User;
use App\Services\NotificationService;

// AI complaint triage (Directive S15).
//
// Two rules shape this design:
//
//  1. "Critical issues must automatically escalate to humans." Escalation
//     is the ONE thing here that happens without waiting for a human —
//     because the directive requires it, and because failing to escalate a
//     critical complaint is the costly error. Escalation only ever ADDS
//     human attention; it never closes, resolves, or answers anything.
//
//  2. Everything else is a suggestion. Severity, category, draft response
//     and resolution are written to a separate ticket_ai_triages row and
//     are only copied onto the ticket when a human applies them. The
//     ticket's own priority and status stay human-owned.
class AiComplaintService
{
    public function __construct(
        private AiGateway $gateway,
        private NotificationService $notifications,
    ) {
    }

    public function triage(SupportTicket $ticket, ?int $userId = null): TicketAiTriage
    {
        $context = [
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'current_category' => $ticket->category,
            'current_priority' => $ticket->priority,
            'current_status' => $ticket->status,
            'opened_at' => optional($ticket->created_at)->toIso8601String(),
            'replies' => $ticket->replies()
                ->where('is_internal_note', false)
                ->orderBy('created_at')
                ->get(['message', 'created_at'])
                ->map(fn ($r) => [
                    'message' => $r->message,
                    'at' => optional($r->created_at)->toIso8601String(),
                ])->all(),
        ];

        $system = <<<'PROMPT'
You triage customer complaints for a travel agency's support desk.

Return ONLY a JSON object, no prose and no code fences:
{"severity": "low"|"medium"|"high"|"critical",
 "category": string,
 "sentiment": "positive"|"neutral"|"negative"|"angry",
 "detected_issues": [string],
 "suggested_response": string,
 "suggested_resolution": string,
 "recommends_escalation": boolean,
 "escalation_reason": string|null}

Severity guidance:
- "critical" = the customer is stranded, a departure is imminent and at
  risk, there is a safety issue, a legal/regulatory threat, or money has
  been taken with no service delivered.
- "high" = the trip is materially affected but not imminent.
- Otherwise medium or low.

Absolute rules:
- NEVER promise a refund, a price, a booking change, or a visa outcome in
  "suggested_response". You cannot authorise any of those. Where the reply
  needs one, write a placeholder like [AGENT: CONFIRM REFUND ELIGIBILITY].
- The response is a DRAFT for a human agent to review, edit and send.
- Base your reading only on the supplied ticket text.
PROMPT;

        $result = $this->gateway->complete(
            $ticket->tenant_id,
            'complaint.triage',
            [['role' => 'user', 'content' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]],
            $system,
            ['max_tokens' => 2000],
            $userId,
        );

        $analysis = $this->decodeJson($result->text);

        $triage = TicketAiTriage::create([
            'tenant_id' => $ticket->tenant_id,
            'support_ticket_id' => $ticket->id,
            'suggested_severity' => $analysis['severity'] ?? null,
            'suggested_category' => $analysis['category'] ?? null,
            'suggested_response' => $analysis['suggested_response'] ?? null,
            'suggested_resolution' => $analysis['suggested_resolution'] ?? null,
            'recommends_escalation' => (bool) ($analysis['recommends_escalation'] ?? false),
            'escalation_reason' => $analysis['escalation_reason'] ?? null,
            'sentiment' => $analysis['sentiment'] ?? null,
            'detected_issues' => $analysis['detected_issues'] ?? [],
        ]);

        // The directive's one mandated automatic action.
        if (($analysis['severity'] ?? null) === 'critical') {
            $this->escalateToHumans($ticket, $analysis['escalation_reason'] ?? 'AI triage classified this complaint as critical.');
        }

        return $triage;
    }

    /**
     * Copies a triage's suggestions onto the ticket — only ever called
     * from an explicit human action.
     */
    public function applyTriage(TicketAiTriage $triage, User $user): SupportTicket
    {
        $ticket = $triage->ticket;

        $update = [];
        if ($triage->suggested_severity) {
            // Map severity onto the ticket's own priority vocabulary.
            $update['priority'] = match ($triage->suggested_severity) {
                'critical' => 'urgent',
                'high' => 'high',
                'medium' => 'normal',
                default => 'low',
            };
        }
        if ($triage->suggested_category) {
            $update['category'] = $triage->suggested_category;
        }

        if (!empty($update)) {
            $ticket->update($update);
        }

        $triage->update(['applied_by' => $user->id, 'applied_at' => now()]);

        return $ticket->fresh();
    }

    /**
     * Escalation adds human attention. It never resolves or answers.
     */
    private function escalateToHumans(SupportTicket $ticket, string $reason): void
    {
        if ($ticket->escalated) {
            return;
        }

        $ticket->update([
            'escalated' => true,
            'escalated_at' => now(),
            'escalation_source' => 'ai_critical',
            'escalation_note' => $reason,
            // Deliberately NOT changing status: a human decides whether
            // this is in progress, and the AI must not appear to be
            // working the ticket.
        ]);

        // Notify the assignee if there is one; otherwise every active user
        // in the tenant, so a critical complaint cannot sit unseen because
        // nobody happened to own it.
        $recipients = $ticket->assigned_to
            ? User::where('id', $ticket->assigned_to)->get()
            : User::where('tenant_id', $ticket->tenant_id)->where('is_active', true)->get();

        foreach ($recipients as $user) {
            $this->notifications->send(
                $ticket->tenant_id,
                $user->id,
                'ticket_escalated',
                'Critical complaint escalated',
                "Ticket #{$ticket->id} \"{$ticket->subject}\" was classified critical and needs human attention. Reason: {$reason}",
                ['support_ticket_id' => $ticket->id],
                ['in_app', 'telegram'],
            );
        }
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
