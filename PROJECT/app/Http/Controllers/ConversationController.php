<?php
namespace App\Http\Controllers;

use App\Models\ApiConnector;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\ApiConnectorService;
use App\Services\ConnectorException;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

// Unified inbox across channels (Directive S3.N).
//
// Outbound sending is honest per channel: email goes through Laravel's
// configured mailer, Telegram through the Phase 7 bot client, and any other
// channel requires an operator-configured connector. A channel with no
// transport records the message with delivery_status 'not_attempted' and
// the reason — it never claims a message was sent when it was not.
class ConversationController extends Controller
{
    public function __construct(
        private ApiConnectorService $connectors,
        private TelegramNotificationService $telegram,
    ) {
    }

    public function index(Request $request)
    {
        $conversations = Conversation::where('tenant_id', $request->user->tenant_id)
            ->when($request->channel, fn ($q, $v) => $q->where('channel', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->assigned_to, fn ($q, $v) => $q->where('assigned_to', $v))
            ->with('customer:id,name,email,phone', 'assignee:id,name')
            ->orderByDesc('last_message_at')
            ->paginate($request->per_page ?? 25);

        return response()->json(['data' => $conversations->items(), 'total' => $conversations->total()]);
    }

    public function show(Request $request, $id)
    {
        $conversation = Conversation::where('tenant_id', $request->user->tenant_id)
            ->with(['messages' => fn ($q) => $q->orderBy('created_at')])
            ->findOrFail($id);

        // Opening a thread clears its unread counter.
        $conversation->update(['unread_count' => 0]);
        $conversation->messages()->whereNull('read_at')->where('direction', 'inbound')->update(['read_at' => now()]);

        return response()->json(['data' => $conversation]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'lead_id' => 'nullable|exists:leads,id',
            'channel' => ['required', Rule::in(Conversation::CHANNELS)],
            'subject' => 'nullable|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if (empty($validated['customer_id']) && empty($validated['lead_id'])) {
            return response()->json(['error' => 'A conversation must be linked to a customer or a lead.'], 422);
        }

        $conversation = Conversation::create([
            'tenant_id' => $request->user->tenant_id,
            'status' => 'open',
            ...$validated,
        ]);

        return response()->json(['data' => $conversation], 201);
    }

    // Records an inbound message (from a webhook, a website chat widget, or
    // manual entry) and bumps the thread.
    public function recordInbound(Request $request, $id)
    {
        $conversation = Conversation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'body' => 'required|string',
            'external_message_id' => 'nullable|string|max:255',
            'attachments' => 'nullable|array',
        ]);

        $message = DB::transaction(function () use ($conversation, $validated) {
            $message = ConversationMessage::create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'body' => $validated['body'],
                'attachments' => $validated['attachments'] ?? null,
                'external_message_id' => $validated['external_message_id'] ?? null,
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'status' => $conversation->status === 'closed' ? 'open' : $conversation->status,
            ]);
            $conversation->increment('unread_count');

            return $message;
        });

        return response()->json(['data' => $message], 201);
    }

    public function reply(Request $request, $id)
    {
        $conversation = Conversation::where('tenant_id', $request->user->tenant_id)
            ->with('customer', 'lead')
            ->findOrFail($id);

        $validated = $request->validate([
            'body' => 'required|string',
            'is_internal_note' => 'boolean',
        ]);

        $isNote = $validated['is_internal_note'] ?? false;

        $message = ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_user_id' => $request->user->id,
            'body' => $validated['body'],
            'is_internal_note' => $isNote,
            // An internal note is never sent anywhere.
            'delivery_status' => $isNote ? null : 'pending',
        ]);

        if (!$isNote) {
            $this->attemptDelivery($conversation, $message);
        }

        $conversation->update(['last_message_at' => now()]);

        return response()->json(['data' => $message->fresh()], 201);
    }

    public function assign(Request $request, $id)
    {
        $conversation = Conversation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate(['assigned_to' => 'required|exists:users,id']);
        $conversation->update($validated);

        return response()->json(['data' => $conversation->fresh()]);
    }

    public function updateStatus(Request $request, $id)
    {
        $conversation = Conversation::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'pending', 'closed'])],
        ]);
        $conversation->update($validated);

        return response()->json(['data' => $conversation->fresh()]);
    }

    /**
     * Attempts real delivery and records the real outcome on the message.
     * Never marks a message sent unless a transport actually accepted it.
     */
    private function attemptDelivery(Conversation $conversation, ConversationMessage $message): void
    {
        $contact = $conversation->customer ?? $conversation->lead;

        try {
            switch ($conversation->channel) {
                case 'email':
                    if (!$contact?->email) {
                        $message->update([
                            'delivery_status' => 'not_attempted',
                            'delivery_error' => 'No email address on record for this contact.',
                        ]);
                        return;
                    }
                    Mail::raw($message->body, function ($mail) use ($contact, $conversation) {
                        $mail->to($contact->email)->subject($conversation->subject ?? 'Message from your travel agent');
                    });
                    $message->update(['delivery_status' => 'sent']);
                    return;

                case 'telegram':
                    if (!$conversation->external_thread_id) {
                        $message->update([
                            'delivery_status' => 'not_attempted',
                            'delivery_error' => 'No Telegram chat id stored on this conversation.',
                        ]);
                        return;
                    }
                    $result = $this->telegram->send($conversation->external_thread_id, $message->body);
                    $message->update([
                        'delivery_status' => $result['sent'] ? 'sent' : 'failed',
                        'delivery_error' => $result['sent'] ? null : ($result['reason'] ?? null),
                    ]);
                    return;

                case 'internal':
                    $message->update(['delivery_status' => 'not_attempted', 'delivery_error' => 'Internal channel — nothing is sent.']);
                    return;

                default:
                    // whatsapp, sms, website_chat — require an operator
                    // connector from the Integration Manager.
                    $connector = ApiConnector::where('tenant_id', $conversation->tenant_id)
                        ->where('category', $conversation->channel)
                        ->where('is_active', true)
                        ->first();

                    if (!$connector || !$connector->endpointFor('send')) {
                        $message->update([
                            'delivery_status' => 'not_attempted',
                            'delivery_error' => "CONTRACT REQUIRED: no active {$conversation->channel} provider "
                                . "with a mapped 'send' endpoint is configured.",
                        ]);
                        return;
                    }

                    $this->connectors->execute($connector, 'send', [
                        'to' => $contact?->phone ?? $conversation->external_thread_id,
                        'message' => $message->body,
                    ], $message->sender_user_id);

                    $message->update(['delivery_status' => 'sent']);
                    return;
            }
        } catch (ConnectorException|\Throwable $e) {
            $message->update(['delivery_status' => 'failed', 'delivery_error' => $e->getMessage()]);
        }
    }
}
