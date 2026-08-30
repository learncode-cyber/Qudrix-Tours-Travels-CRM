<?php
namespace App\Http\Controllers;
use App\Models\HajjUmrahGroup;
use App\Models\HajjPackage;
use App\Models\UmrahPackage;
use Illuminate\Http\Request;

class HajjUmrahGroupController extends Controller
{
    public function index(Request $request)
    {
        $groups = HajjUmrahGroup::where('tenant_id', $request->user->tenant_id)
            ->when($request->package_type, fn ($q) => $q->where('package_type', $request->package_type))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->withCount('pilgrims')
            ->orderBy('departure_date')
            ->paginate($request->per_page ?? 20);
        return response()->json(['data' => $groups->items()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'package_type' => 'required|in:hajj,umrah',
            'package_id' => 'required|integer',
            'name' => 'required|string',
            'departure_date' => 'required|date',
            'return_date' => 'required|date|after:departure_date',
            'group_leader_id' => 'nullable|exists:users,id',
            'agent_id' => 'nullable|exists:vendors,id',
            'capacity' => 'required|integer|min:1',
        ]);

        $packageModel = $validated['package_type'] === 'hajj' ? HajjPackage::class : UmrahPackage::class;
        $packageModel::where('tenant_id', $request->user->tenant_id)->findOrFail($validated['package_id']);

        $group = HajjUmrahGroup::create(['tenant_id' => $request->user->tenant_id, 'status' => 'planned', ...$validated]);
        return response()->json(['data' => $group], 201);
    }

    public function show(Request $request, $id)
    {
        $group = HajjUmrahGroup::where('tenant_id', $request->user->tenant_id)
            ->with('pilgrims', 'groupLeader', 'agent')
            ->findOrFail($id);
        return response()->json(['data' => $group->toArray() + ['seats_available' => $group->seatsAvailable(), 'package' => $group->package()]]);
    }

    public function update(Request $request, $id)
    {
        $group = HajjUmrahGroup::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'departure_date' => 'sometimes|date',
            'return_date' => 'sometimes|date|after:departure_date',
            'group_leader_id' => 'nullable|exists:users,id',
            'agent_id' => 'nullable|exists:vendors,id',
            'capacity' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:planned,confirmed,departed,completed,cancelled',
        ]);
        $group->update($validated);
        return response()->json(['data' => $group]);
    }

    public function report(Request $request, $id)
    {
        $group = HajjUmrahGroup::where('tenant_id', $request->user->tenant_id)->with('pilgrims')->findOrFail($id);
        $pilgrims = $group->pilgrims;

        return response()->json(['data' => [
            'group' => $group->name,
            'total_pilgrims' => $pilgrims->count(),
            'seats_available' => $group->seatsAvailable(),
            'by_status' => $pilgrims->groupBy('status')->map->count(),
            'total_amount_due' => $pilgrims->sum('amount_due'),
            'total_amount_paid' => $pilgrims->sum('amount_paid'),
            'total_balance' => $pilgrims->sum(fn ($p) => $p->balanceDue()),
            'unassigned_rooms' => $pilgrims->whereNull('room_number')->count(),
        ]]);
    }
}
