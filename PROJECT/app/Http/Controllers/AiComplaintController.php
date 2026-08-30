<?php
namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\TicketAiTriage;
use App\Services\Ai\AiComplaintService;
use App\Services\Ai\AiProviderException;
use Illuminate\Http\Request;

class AiComplaintController extends Controller
{
    public function __construct(private AiComplaintService $complaints)
    {
    }

    // Runs triage. Suggestions are stored separately from the ticket;
    // only a critical severity auto-escalates (adds human attention).
    public function triage(Request $request, $ticketId)
    {
        $ticket = SupportTicket::where('tenant_id', $request->user->tenant_id)
            ->with('replies')
            ->findOrFail($ticketId);

        try {
            $triage = $this->complaints->triage($ticket, $request->user->id);
        } catch (AiProviderException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json([
            'data' => $triage,
            'is_suggestion' => true,
            'applied_to_ticket' => false,
            'note' => 'Severity, category, and drafts are suggestions. Only a critical severity '
                . 'automatically escalates the ticket for human attention; nothing is answered or resolved automatically.',
        ], 201);
    }

    public function show(Request $request, $ticketId)
    {
        $ticket = SupportTicket::where('tenant_id', $request->user->tenant_id)->findOrFail($ticketId);

        return response()->json([
            'data' => TicketAiTriage::where('support_ticket_id', $ticket->id)->latest()->get(),
        ]);
    }

    // Explicit human action: copy the triage's suggestions onto the ticket.
    public function apply(Request $request, $ticketId, $triageId)
    {
        $ticket = SupportTicket::where('tenant_id', $request->user->tenant_id)->findOrFail($ticketId);
        $triage = TicketAiTriage::where('tenant_id', $request->user->tenant_id)
            ->where('support_ticket_id', $ticket->id)
            ->findOrFail($triageId);

        if ($triage->isApplied()) {
            return response()->json(['error' => 'This triage has already been applied.'], 422);
        }

        $updated = $this->complaints->applyTriage($triage, $request->user);

        return response()->json(['data' => ['ticket' => $updated, 'triage' => $triage->fresh()]]);
    }
}
