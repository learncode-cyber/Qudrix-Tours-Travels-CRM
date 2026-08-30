<?php
namespace App\Http\Controllers;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = SupportTicket::where('tenant_id', $request->user->tenant_id)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->priority, fn ($q) => $q->where('priority', $request->priority))
            ->latest()
            ->paginate(20);
        return response()->json(['data' => $tickets->items()]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'assigned_to' => 'nullable|exists:users,id',
            'subject' => 'required|string',
            'description' => 'required|string',
            'category' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);
        $ticket = SupportTicket::create([
            'tenant_id' => $request->user->tenant_id,
            'status' => 'open',
            ...$validated,
        ]);
        return response()->json(['data' => $ticket], 201);
    }
    public function show(Request $request, $id)
    {
        $ticket = SupportTicket::where('tenant_id', $request->user->tenant_id)
            ->with('replies')
            ->findOrFail($id);
        return response()->json(['data' => $ticket]);
    }
    public function updateStatus(Request $request, $id)
    {
        $ticket = SupportTicket::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);
        $ticket->status = $validated['status'];
        if ($validated['status'] === 'resolved') {
            $ticket->resolved_at = now();
        }
        $ticket->save();
        return response()->json(['data' => $ticket]);
    }
    public function escalate(Request $request, $id)
    {
        $ticket = SupportTicket::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'escalated_to' => 'required|exists:users,id',
        ]);
        $ticket->update([
            'escalated' => true,
            'escalated_at' => now(),
            'escalated_to' => $validated['escalated_to'],
        ]);
        return response()->json(['data' => $ticket]);
    }
    public function reply(Request $request, $id)
    {
        $ticket = SupportTicket::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'message' => 'required|string',
            'is_internal_note' => 'boolean',
        ]);
        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user->id,
            'is_internal_note' => $validated['is_internal_note'] ?? false,
            'message' => $validated['message'],
        ]);
        return response()->json(['data' => $reply], 201);
    }
}
