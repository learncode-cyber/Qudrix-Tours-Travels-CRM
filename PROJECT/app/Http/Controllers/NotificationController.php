<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Invoice;
use App\Models\Task;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function index(Request $request)
    {
        $query = Notification::where('tenant_id', $request->user->tenant_id)
            ->where('user_id', $request->user->id);

        if ($request->unread_only) {
            $query->whereNull('read_at');
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $notifications->items(),
            'unread_count' => Notification::where('tenant_id', $request->user->tenant_id)
                ->where('user_id', $request->user->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where('tenant_id', $request->user->tenant_id)
            ->where('user_id', $request->user->id)
            ->findOrFail($id);

        $notification->update(['read_at' => now()]);

        return response()->json(['data' => $notification]);
    }

    /**
     * Staff-triggered sweep for overdue/near-due invoices. A true
     * "scheduled" version needs Laravel's task scheduler running under a
     * real cron (php artisan schedule:run every minute) — that
     * execution can't be verified in this sandbox, so this is exposed as
     * an explicit action a staff member (or an external cron hitting
     * this endpoint) triggers, rather than claiming an automatic
     * schedule that isn't actually running anywhere.
     */
    public function sendPaymentReminders(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $dueSoon = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['sent', 'partially_paid'])
            ->where('due_date', '<=', now()->addDays(3))
            ->with('customer')
            ->get();

        $sent = [];
        foreach ($dueSoon as $invoice) {
            if (!$invoice->customer || !$invoice->customer->email) {
                continue;
            }

            $result = $this->notifications->notify(
                $tenantId,
                'payment_reminder',
                'email',
                ['to' => $invoice->customer->email, 'customer_id' => $invoice->customer_id],
                [
                    'customer_name' => $invoice->customer->name,
                    'invoice_number' => $invoice->invoice_number,
                    'balance_due' => $invoice->balance_due,
                    'due_date' => optional($invoice->due_date)->toDateString(),
                    'default_message' => "Reminder: invoice {$invoice->invoice_number} of {$invoice->balance_due} {$invoice->currency} is due {$invoice->due_date}.",
                ],
                ['type' => 'invoice', 'id' => $invoice->id]
            );

            $sent[] = ['invoice_id' => $invoice->id, 'status' => $result['result']['status']];
        }

        return response()->json(['message' => 'Payment reminder sweep complete', 'data' => $sent]);
    }

    /**
     * Same pattern as sendPaymentReminders() — staff/external-cron
     * triggered, notifies task assignees in-app for tasks due today or
     * overdue.
     */
    public function sendFollowUpReminders(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $dueTasks = Task::where('tenant_id', $tenantId)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->endOfDay())
            ->whereNotNull('assigned_to')
            ->get();

        $sent = [];
        foreach ($dueTasks as $task) {
            $result = $this->notifications->notify(
                $tenantId,
                'follow_up_due',
                'in_app',
                ['user_id' => $task->assigned_to],
                [
                    'task_title' => $task->title,
                    'due_date' => optional($task->due_date)->toDateString(),
                    'default_message' => "Follow-up due: {$task->title}",
                ],
                ['type' => 'task', 'id' => $task->id]
            );

            $sent[] = ['task_id' => $task->id, 'status' => $result['result']['status']];
        }

        return response()->json(['message' => 'Follow-up reminder sweep complete', 'data' => $sent]);
    }
}
