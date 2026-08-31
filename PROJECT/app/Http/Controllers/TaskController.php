<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::where('tenant_id', $request->user->tenant_id);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->priority) {
            $query->where('priority', $request->priority);
        }

        if ($request->assigned_to) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->my_tasks) {
            $query->where('assigned_to', $request->user->id);
        }

        if ($request->overdue) {
            $query->where('due_date', '<', now())
                ->where('status', '!=', 'completed');
        }

        $tasks = $query->orderBy('due_date', 'asc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $tasks->items(),
            'pagination' => [
                'total' => $tasks->total(),
                'per_page' => $tasks->perPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:task,followup,reminder,meeting',
            'priority' => 'required|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'required|date',
            'related_entity_type' => 'nullable|string',
            'related_entity_id' => 'nullable|integer',
        ]);

        $task = Task::create([
            'tenant_id' => $request->user->tenant_id,
            'status' => 'open',
            ...$validated
        ]);

        return response()->json([
            'message' => 'Task created successfully',
            'data' => $task
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $task = Task::where('tenant_id', $request->user->tenant_id)
            ->with('assignee')
            ->findOrFail($id);

        return response()->json(['data' => $task]);
    }

    public function update(Request $request, $id)
    {
        $task = Task::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:open,in_progress,completed,cancelled',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'sometimes|date',
        ]);

        $task->update($validated);

        return response()->json([
            'message' => 'Task updated successfully',
            'data' => $task
        ]);
    }

    public function markComplete(Request $request, $id)
    {
        $task = Task::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $task->markComplete();

        return response()->json([
            'message' => 'Task marked as completed',
            'data' => $task
        ]);
    }

    public function markIncomplete(Request $request, $id)
    {
        $task = Task::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $task->markIncomplete();

        return response()->json([
            'message' => 'Task marked as incomplete',
            'data' => $task
        ]);
    }

    public function getTaskStats(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $stats = [
            'total' => Task::where('tenant_id', $tenantId)->count(),
            'open' => Task::where('tenant_id', $tenantId)->where('status', 'open')->count(),
            'completed' => Task::where('tenant_id', $tenantId)->where('status', 'completed')->count(),
            'overdue' => Task::where('tenant_id', $tenantId)
                ->where('due_date', '<', now())
                ->where('status', '!=', 'completed')
                ->count(),
            'by_priority' => Task::where('tenant_id', $tenantId)
                ->selectRaw('priority, count(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority'),
            'my_pending' => Task::where('tenant_id', $tenantId)
                ->where('assigned_to', $request->user->id)
                ->where('status', '!=', 'completed')
                ->count(),
        ];

        return response()->json(['data' => $stats]);
    }

    // apiResource('tasks', ...) registers DELETE /tasks/{task} routed to
    // destroy() by Laravel convention — this was named delete(), so that
    // route 500'd with "method does not exist" the moment anything
    // called it.
    public function destroy(Request $request, $id)
    {
        $task = Task::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }
}
