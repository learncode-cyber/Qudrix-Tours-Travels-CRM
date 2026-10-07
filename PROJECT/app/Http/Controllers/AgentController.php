<?php
namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\CommissionEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $agents = Agent::where('tenant_id', $request->user->tenant_id)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['data' => $agents->items(), 'pagination' => [
            'total' => $agents->total(),
            'per_page' => $agents->perPage(),
            'current_page' => $agents->currentPage(),
            'last_page' => $agents->lastPage(),
        ]]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'agent_code' => 'required|string|unique:agents,agent_code',
            'name' => 'required|string',
            'email' => 'nullable|email',
            'phone' => 'required|string',
            'company_name' => 'nullable|string',
            'commission_type' => 'required|in:percentage,fixed',
            'commission_rate' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $agent = Agent::create(['tenant_id' => $request->user->tenant_id, 'status' => 'active', ...$validated]);

        return response()->json(['data' => $agent], 201);
    }

    public function show(Request $request, $id)
    {
        $agent = Agent::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        return response()->json(['data' => $agent]);
    }

    public function update(Request $request, $id)
    {
        $agent = Agent::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'email' => 'nullable|email',
            'phone' => 'sometimes|string',
            'company_name' => 'nullable|string',
            'commission_type' => 'sometimes|in:percentage,fixed',
            'commission_rate' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:active,inactive,suspended',
            'notes' => 'nullable|string',
        ]);

        $agent->update($validated);

        return response()->json(['data' => $agent]);
    }

    public function destroy(Request $request, $id)
    {
        $agent = Agent::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $agent->delete();

        return response()->json(['message' => 'Agent removed']);
    }

    /**
     * Referral performance summary for one agent — leads/bookings
     * attributed to them and their commission ledger snapshot.
     */
    public function performance(Request $request, $id)
    {
        $agent = Agent::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        return response()->json(['data' => [
            'agent' => $agent,
            'leads_count' => $agent->leads()->count(),
            'bookings_count' => $agent->bookings()->count(),
            'total_commission_earned' => $agent->total_commission_earned,
            'total_commission_paid' => $agent->total_commission_paid,
            'commission_balance' => $agent->total_commission_earned - $agent->total_commission_paid,
        ]]);
    }

    /**
     * Full commission ledger for one agent (MASTER_PROJECT_AUDIT.md P2) —
     * the line-item history that Agent's running totals alone can't
     * provide for reconciliation.
     */
    public function commissionLedger(Request $request, $id)
    {
        $agent = Agent::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $entries = CommissionEntry::where('tenant_id', $request->user->tenant_id)
            ->where('agent_id', $agent->id)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['data' => $entries->items(), 'pagination' => [
            'total' => $entries->total(),
            'per_page' => $entries->perPage(),
            'current_page' => $entries->currentPage(),
            'last_page' => $entries->lastPage(),
        ]]);
    }

    /**
     * Records a manual payout to the agent as a ledger entry and updates
     * the cached running total on Agent atomically. Does not allow paying
     * out more than the current outstanding balance.
     */
    public function payCommission(Request $request, $id)
    {
        $agent = Agent::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        $balance = $agent->total_commission_earned - $agent->total_commission_paid;
        if ($validated['amount'] > $balance) {
            return response()->json(['error' => "Payout amount exceeds outstanding balance ({$balance})"], 422);
        }

        $entry = DB::transaction(function () use ($agent, $validated, $request) {
            $agent->increment('total_commission_paid', $validated['amount']);
            $newBalance = $agent->total_commission_earned - $agent->fresh()->total_commission_paid;

            return CommissionEntry::create([
                'tenant_id' => $request->user->tenant_id,
                'agent_id' => $agent->id,
                'type' => 'paid',
                'amount' => $validated['amount'],
                'balance_after' => $newBalance,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user->id,
            ]);
        });

        return response()->json(['data' => $entry], 201);
    }
}
