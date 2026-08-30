<?php
namespace App\Http\Controllers;
use App\Models\HajjUmrahGroup;
use App\Models\Pilgrim;
use Illuminate\Http\Request;

class PilgrimController extends Controller
{
    public function index(Request $request)
    {
        $pilgrims = Pilgrim::where('tenant_id', $request->user->tenant_id)
            ->when($request->hajj_umrah_group_id, fn ($q) => $q->where('hajj_umrah_group_id', $request->hajj_umrah_group_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->paginate($request->per_page ?? 20);
        return response()->json(['data' => $pilgrims->items()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'hajj_umrah_group_id' => 'required|exists:hajj_umrah_groups,id',
            'booking_id' => 'nullable|exists:bookings,id',
            'customer_id' => 'nullable|exists:customers,id',
            'name' => 'required|string',
            'passport_number' => 'nullable|string',
            'passport_expiry' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'date_of_birth' => 'nullable|date',
            'mahram_name' => 'nullable|string',
            'amount_due' => 'nullable|numeric|min:0',
        ]);

        $group = HajjUmrahGroup::where('tenant_id', $request->user->tenant_id)->findOrFail($validated['hajj_umrah_group_id']);
        if ($group->seatsAvailable() <= 0) {
            return response()->json(['error' => 'Group is at full capacity'], 400);
        }

        $pilgrim = Pilgrim::create(['tenant_id' => $request->user->tenant_id, 'status' => 'registered', 'payment_status' => 'pending', 'amount_paid' => 0, ...$validated]);
        return response()->json(['data' => $pilgrim], 201);
    }

    public function show(Request $request, $id)
    {
        $pilgrim = Pilgrim::where('tenant_id', $request->user->tenant_id)->with('group', 'hotel', 'visaApplication')->findOrFail($id);
        return response()->json(['data' => $pilgrim]);
    }

    public function update(Request $request, $id)
    {
        $pilgrim = Pilgrim::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'passport_number' => 'nullable|string',
            'passport_expiry' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'date_of_birth' => 'nullable|date',
            'mahram_name' => 'nullable|string',
            'status' => 'sometimes|in:registered,confirmed,travelled,completed,cancelled',
        ]);
        $pilgrim->update($validated);
        return response()->json(['data' => $pilgrim]);
    }

    public function assignRoom(Request $request, $id)
    {
        $pilgrim = Pilgrim::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'room_number' => 'required|string',
            'hotel_id' => 'nullable|exists:hotels,id',
        ]);
        $pilgrim->update($validated);
        return response()->json(['data' => $pilgrim]);
    }

    public function assignTransport(Request $request, $id)
    {
        $pilgrim = Pilgrim::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate(['transport_assignment' => 'required|string']);
        $pilgrim->update($validated);
        return response()->json(['data' => $pilgrim]);
    }

    public function recordPayment(Request $request, $id)
    {
        $pilgrim = Pilgrim::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate(['amount' => 'required|numeric|min:0.01']);
        $pilgrim->increment('amount_paid', $validated['amount']);
        $pilgrim->refresh();
        $pilgrim->update(['payment_status' => $pilgrim->balanceDue() <= 0 ? 'paid' : 'partial']);
        return response()->json(['data' => $pilgrim]);
    }
}
