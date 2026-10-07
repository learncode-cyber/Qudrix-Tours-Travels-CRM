<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Lead;
use App\Services\AI\AISalesAgentService;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(protected AISalesAgentService $salesAgent)
    {
    }

    public function index(Request $request)
    {
        $query = Conversation::where('tenant_id', $request->user->tenant_id)
            ->with('lead', 'customer', 'assignedStaff');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json(['data' => $query->orderBy('updated_at', 'desc')->paginate($request->per_page ?? 20)]);
    }

    public function show(Request $request, $id)
    {
        $conversation = Conversation::where('tenant_id', $request->user->tenant_id)
            ->with('messages', 'lead', 'customer')
            ->findOrFail($id);

        return response()->json(['data' => $conversation]);
    }

    /**
     * Starts a conversation, creating/finding a Lead behind it so this
     * plugs into the same CRM chain fixed in Phase 8 (a chat is a lead
     * source, same as a form submission).
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'channel' => 'nullable|string',
        ]);

        $tenantId = $request->user->tenant_id;

        $lead = null;
        if (!empty($validated['email'])) {
            $lead = Lead::firstOrCreate(
                ['tenant_id' => $tenantId, 'email' => $validated['email']],
                ['name' => $validated['name'], 'phone' => $validated['phone'] ?? null, 'source' => 'ai_chat', 'status' => 'new']
            );
        }

        $conversation = Conversation::create([
            'tenant_id' => $tenantId,
            'lead_id' => $lead?->id,
            'channel' => $validated['channel'] ?? 'web_chat',
            'status' => 'active',
        ]);

        return response()->json(['data' => $conversation], 201);
    }

    public function sendMessage(Request $request, $id)
    {
        $validated = $request->validate(['message' => 'required|string|max:2000']);

        $conversation = Conversation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($conversation->status === 'closed') {
            return response()->json(['error' => 'This conversation is closed'], 400);
        }

        $result = $this->salesAgent->sendMessage($conversation, $validated['message']);

        return response()->json(['data' => $result]);
    }

    public function escalate(Request $request, $id)
    {
        $conversation = Conversation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $conversation->update(['status' => 'needs_human', 'escalated_at' => now()]);

        return response()->json(['data' => $conversation]);
    }

    public function assign(Request $request, $id)
    {
        $validated = $request->validate(['user_id' => 'required|exists:users,id']);

        $conversation = Conversation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $conversation->update(['assigned_to' => $validated['user_id'], 'status' => 'escalated']);

        return response()->json(['data' => $conversation->load('assignedStaff')]);
    }

    public function close(Request $request, $id)
    {
        $conversation = Conversation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $conversation->update(['status' => 'closed']);

        return response()->json(['data' => $conversation]);
    }
}
