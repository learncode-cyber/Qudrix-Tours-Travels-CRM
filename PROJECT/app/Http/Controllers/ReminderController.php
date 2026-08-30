<?php
namespace App\Http\Controllers;
use App\Models\Reminder;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $reminders = Reminder::where('tenant_id', $request->user->tenant_id)
            ->where('user_id', $request->user->id)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderBy('remind_at')
            ->paginate(20);
        return response()->json(['data' => $reminders->items()]);
    }
    public function due(Request $request)
    {
        $reminders = Reminder::where('tenant_id', $request->user->tenant_id)
            ->where('user_id', $request->user->id)
            ->where('status', 'pending')
            ->where('remind_at', '<=', now())
            ->orderBy('remind_at')
            ->get();
        return response()->json(['data' => $reminders]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'remindable_type' => 'nullable|string',
            'remindable_id' => 'nullable|integer',
            'title' => 'required|string',
            'remind_at' => 'required|date',
        ]);
        $reminder = Reminder::create([
            'tenant_id' => $request->user->tenant_id,
            'user_id' => $request->user->id,
            'status' => 'pending',
            ...$validated,
        ]);
        return response()->json(['data' => $reminder], 201);
    }
    public function complete(Request $request, $id)
    {
        $reminder = Reminder::where('tenant_id', $request->user->tenant_id)
            ->where('user_id', $request->user->id)
            ->findOrFail($id);
        $reminder->update(['status' => 'completed']);
        return response()->json(['data' => $reminder]);
    }
    public function destroy(Request $request, $id)
    {
        $reminder = Reminder::where('tenant_id', $request->user->tenant_id)
            ->where('user_id', $request->user->id)
            ->findOrFail($id);
        $reminder->delete();
        return response()->json(['message' => 'Reminder deleted']);
    }
}
