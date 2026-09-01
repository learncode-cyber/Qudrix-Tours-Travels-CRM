<?php

namespace App\Http\Controllers;

use App\Models\Communication;
use App\Models\Customer;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function index(Request $request)
    {
        $query = Communication::where('tenant_id', $request->user->tenant_id);

        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $communications = $query->latest()->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $communications->items(),
            'pagination' => [
                'total' => $communications->total(),
                'per_page' => $communications->perPage(),
                'current_page' => $communications->currentPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'type' => 'required|in:email,sms,whatsapp,call,meeting,note',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        $communication = Communication::create([
            'tenant_id' => $request->user->tenant_id,
            'created_by' => $request->user->id,
            'status' => 'sent',
            'sent_at' => now(),
            ...$validated
        ]);

        return response()->json([
            'message' => 'Communication logged successfully',
            'data' => $communication
        ], 201);
    }

    public function getCustomerCommunications(Request $request, $customerId)
    {
        Customer::where('tenant_id', $request->user->tenant_id)->findOrFail($customerId);

        $communications = Communication::where('tenant_id', $request->user->tenant_id)
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $communications->items(),
            'pagination' => [
                'total' => $communications->total(),
                'per_page' => $communications->perPage(),
            ]
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $communication = Communication::where('tenant_id', $request->user->tenant_id)
            ->findOrFail($id);

        $communication->markAsRead();

        return response()->json([
            'message' => 'Communication marked as read',
            'data' => $communication
        ]);
    }

    public function getCommunicationStats(Request $request)
    {
        $stats = [
            'total' => Communication::where('tenant_id', $request->user->tenant_id)->count(),
            'today' => Communication::where('tenant_id', $request->user->tenant_id)
                ->whereDate('created_at', today())
                ->count(),
            'by_type' => Communication::where('tenant_id', $request->user->tenant_id)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'unread' => Communication::where('tenant_id', $request->user->tenant_id)
                ->where('status', 'sent')
                ->count(),
        ];

        return response()->json(['data' => $stats]);
    }
}
