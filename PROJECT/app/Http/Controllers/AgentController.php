<?php
namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\AgentPayout;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

// B2B reseller agent management (Directive S3.L).
class AgentController extends Controller
{
    public function index(Request $request)
    {
        $agents = Agent::where('tenant_id', $request->user->tenant_id)
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->kyc_status, fn ($q, $v) => $q->where('kyc_status', $v))
            ->withCount('bookings', 'leads')
            ->paginate($request->per_page ?? 20);

        return response()->json(['data' => $agents->items(), 'total' => $agents->total()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'agency_name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'country' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $agent = Agent::create([
            'tenant_id' => $request->user->tenant_id,
            // Registration always starts unapproved; an agent cannot
            // transact until a human approves them.
            'status' => 'pending',
            'kyc_status' => 'not_submitted',
            'balance' => 0,
            'agent_code' => 'AG-' . strtoupper(bin2hex(random_bytes(3))),
            ...$validated,
        ]);

        return response()->json(['data' => $agent], 201);
    }

    public function show(Request $request, $id)
    {
        $agent = Agent::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        return response()->json(['data' => $agent->toArray() + [
            'unpaid_commission' => $agent->unpaidCommission(),
            'can_transact' => $agent->canTransact(),
        ]]);
    }

    public function submitKyc(Request $request, $id)
    {
        $agent = Agent::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $agent->update(['kyc_status' => 'submitted']);

        return response()->json([
            'data' => $agent,
            'note' => 'KYC documents are uploaded via POST /documents with documentable_type=agent.',
        ]);
    }

    public function decide(Request $request, $id)
    {
        $agent = Agent::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'suspended'])],
            'rejection_reason' => 'nullable|string',
        ]);

        if ($validated['status'] === 'approved' && $agent->kyc_status !== 'verified') {
            return response()->json([
                'error' => 'KYC must be verified before an agent can be approved.',
            ], 422);
        }

        $agent->update([
            ...$validated,
            'approved_by' => $validated['status'] === 'approved' ? $request->user->id : null,
            'approved_at' => $validated['status'] === 'approved' ? now() : null,
        ]);

        return response()->json(['data' => $agent->fresh()]);
    }

    public function verifyKyc(Request $request, $id)
    {
        $agent = Agent::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'kyc_status' => ['required', Rule::in(['verified', 'rejected'])],
        ]);

        $agent->update($validated);

        return response()->json(['data' => $agent->fresh()]);
    }

    // Records commission for a booking. The amount is DERIVED from the
    // booking's real total and the agent's configured rate — never taken
    // from the request, so it cannot be tampered with.
    public function recordCommission(Request $request, $id)
    {
        $agent = Agent::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if (!$agent->canTransact()) {
            return response()->json(['error' => 'This agent is not approved to transact.'], 422);
        }

        $validated = $request->validate(['booking_id' => 'required|exists:bookings,id']);

        $booking = Booking::where('tenant_id', $agent->tenant_id)->findOrFail($validated['booking_id']);

        if (AgentCommission::where('booking_id', $booking->id)->where('agent_id', $agent->id)->exists()) {
            return response()->json(['error' => 'Commission for this booking is already recorded.'], 422);
        }

        $amount = AgentCommission::calculate((float) $booking->total_amount, (float) $agent->commission_rate);

        $commission = AgentCommission::create([
            'tenant_id' => $agent->tenant_id,
            'agent_id' => $agent->id,
            'booking_id' => $booking->id,
            'booking_amount' => $booking->total_amount,
            'commission_rate' => $agent->commission_rate,
            'commission_amount' => $amount,
            'currency' => $booking->currency ?? 'USD',
            'status' => 'pending',
        ]);

        return response()->json(['data' => $commission], 201);
    }

    public function approveCommission(Request $request, $commissionId)
    {
        $commission = AgentCommission::where('tenant_id', $request->user->tenant_id)->findOrFail($commissionId);

        if ($commission->status !== 'pending') {
            return response()->json(['error' => 'Only a pending commission can be approved.'], 422);
        }

        DB::transaction(function () use ($commission) {
            $commission->update(['status' => 'approved']);
            // Balance is what the agency owes the agent.
            $commission->agent->increment('balance', (float) $commission->commission_amount);
        });

        return response()->json(['data' => $commission->fresh()]);
    }

    // Pays out approved, unpaid commissions in one transaction so the
    // balance, the payout, and the commission rows can never disagree.
    public function createPayout(Request $request, $id)
    {
        $agent = Agent::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'method' => 'nullable|string|max:64',
            'reference' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ]);

        $pending = AgentCommission::where('agent_id', $agent->id)
            ->where('status', 'approved')
            ->whereNull('agent_payout_id')
            ->get();

        if ($pending->isEmpty()) {
            return response()->json(['error' => 'This agent has no approved unpaid commission.'], 422);
        }

        $total = round((float) $pending->sum('commission_amount'), 2);

        $payout = DB::transaction(function () use ($agent, $pending, $total, $validated, $request) {
            $payout = AgentPayout::create([
                'tenant_id' => $agent->tenant_id,
                'agent_id' => $agent->id,
                'amount' => $total,
                'currency' => $pending->first()->currency ?? 'USD',
                'status' => 'pending',
                'processed_by' => $request->user->id,
                ...$validated,
            ]);

            AgentCommission::whereIn('id', $pending->pluck('id'))
                ->update(['agent_payout_id' => $payout->id, 'status' => 'paid']);

            $agent->decrement('balance', $total);

            return $payout;
        });

        return response()->json(['data' => $payout->fresh()->load('commissions')], 201);
    }

    public function markPayoutPaid(Request $request, $payoutId)
    {
        $payout = AgentPayout::where('tenant_id', $request->user->tenant_id)->findOrFail($payoutId);

        if ($payout->status === 'paid') {
            return response()->json(['error' => 'This payout is already marked paid.'], 422);
        }

        $payout->update(['status' => 'paid', 'paid_at' => now()]);

        return response()->json(['data' => $payout->fresh()]);
    }

    // Real performance figures for one agent.
    public function performance(Request $request, $id)
    {
        $agent = Agent::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $bookings = Booking::where('agent_id', $agent->id);
        $bookingCount = (clone $bookings)->count();

        return response()->json(['data' => [
            'agent' => ['id' => $agent->id, 'agency_name' => $agent->agency_name, 'status' => $agent->status],
            'leads_generated' => $agent->leads()->count(),
            'bookings' => $bookingCount,
            'booking_value' => round((float) (clone $bookings)->sum('total_amount'), 2),
            'average_booking_value' => $bookingCount > 0
                ? round((float) (clone $bookings)->sum('total_amount') / $bookingCount, 2)
                : null,
            'commission_earned' => round((float) $agent->commissions()->whereIn('status', ['approved', 'paid'])->sum('commission_amount'), 2),
            'commission_paid' => round((float) $agent->commissions()->where('status', 'paid')->sum('commission_amount'), 2),
            'commission_outstanding' => $agent->unpaidCommission(),
            'current_balance' => (float) $agent->balance,
        ]]);
    }
}
