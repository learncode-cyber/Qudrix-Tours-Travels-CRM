<?php
namespace App\Http\Controllers;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = Notification::where('tenant_id', $request->user->tenant_id)
            ->where('user_id', $request->user->id)
            ->when($request->unread_only, fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate($request->per_page ?? 20);
        return response()->json(['data' => $notifications->items()]);
    }

    public function markRead(Request $request, $id)
    {
        $notification = Notification::where('tenant_id', $request->user->tenant_id)
            ->where('user_id', $request->user->id)
            ->findOrFail($id);
        $notification->update(['read_at' => now()]);
        return response()->json(['data' => $notification]);
    }

    public function markAllRead(Request $request)
    {
        Notification::where('tenant_id', $request->user->tenant_id)
            ->where('user_id', $request->user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function unreadCount(Request $request)
    {
        $count = Notification::where('tenant_id', $request->user->tenant_id)
            ->where('user_id', $request->user->id)
            ->whereNull('read_at')
            ->count();
        return response()->json(['data' => ['unread_count' => $count]]);
    }
}
