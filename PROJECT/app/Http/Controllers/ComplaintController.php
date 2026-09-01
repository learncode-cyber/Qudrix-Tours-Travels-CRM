<?php
namespace App\Http\Controllers;
use App\Models\Complaint;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $query = Complaint::where('tenant_id', $request->user->tenant_id);
        if ($request->status) $query->where('status', $request->status);
        return response()->json(['data' => $query->paginate(20)->items()]);
    }
    public function create(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'customer_id' => 'required|exists:customers,id',
            'title' => 'required|string',
            'description' => 'required|string',
            'category' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);
        $complaint = Complaint::create(['tenant_id' => $request->user->tenant_id, 'status' => 'open', ...$validated]);
        return response()->json(['data' => $complaint], 201);
    }
    public function updateStatus(Request $request, $id)
    {
        $complaint = Complaint::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $complaint->update(['status' => $request->status]);
        if ($request->status === 'resolved') $complaint->update(['resolution_date' => now()]);
        return response()->json(['data' => $complaint]);
    }
}
