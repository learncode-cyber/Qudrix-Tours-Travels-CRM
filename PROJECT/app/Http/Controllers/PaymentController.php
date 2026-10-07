<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\CommissionEntry;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;

/**
 * PHASE 3: this controller did not exist at all — the Payment model and
 * its table were present but nothing in the application could ever
 * create, list, or query a payment record.
 */
class PaymentController extends Controller
{
    public function __construct(protected AnalyticsService $analytics)
    {
    }

    public function index(Request $request)
    {
        $query = Payment::where('tenant_id', $request->user->tenant_id)
            ->with('invoice', 'booking');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->invoice_id) {
            $query->where('invoice_id', $request->invoice_id);
        }

        $payments = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $payments->items(),
            'pagination' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required_without:booking_id|nullable|exists:invoices,id',
            'booking_id' => 'required_without:invoice_id|nullable|exists:bookings,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'transaction_id' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:100',
            'status' => 'required|in:pending,completed,failed,refunded',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $tenantId = $request->user->tenant_id;
        $invoice = null;

        if (!empty($validated['invoice_id'])) {
            $invoice = Invoice::where('tenant_id', $tenantId)->findOrFail($validated['invoice_id']);
        }

        // Commission is computed from the rate configured on the signed
        // Proposal behind this invoice, if any — not invented at
        // payment time. If no rate was configured, no commission is
        // recorded (null, not a fabricated 0%).
        $commissionRate = $invoice?->proposal?->commission_rate;
        $commissionAmount = $commissionRate !== null && ($validated['status'] === 'completed')
            ? round($validated['amount'] * ($commissionRate / 100), 2)
            : null;

        $payment = Payment::create([
            'tenant_id' => $tenantId,
            ...$validated,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'paid_at' => $validated['paid_at'] ?? ($validated['status'] === 'completed' ? now() : null),
        ]);

        if ($invoice) {
            $invoice->recalculatePaidAmount();
        }

        // PHASE 12: this is now the real place revenue data enters the
        // Analytics table — recordMetric() existed since an earlier
        // phase but was never called anywhere, meaning the whole
        // analytics pipeline had no data source at all.
        if ($validated['status'] === 'completed') {
            $this->analytics->recordMetric($tenantId, 'revenue', (float) $validated['amount']);
        }

        // MASTER_PROJECT_AUDIT.md P2: referring-agent commission (separate
        // from the supplier-side commission above) is earned automatically
        // when a completed payment lands against a booking that has an
        // agent_id — this is the "earned" side of the ledger; payout is a
        // separate manual action via AgentController@payCommission.
        if ($validated['status'] === 'completed' && $payment->booking?->agent_id) {
            $agent = $payment->booking->agent;
            $commissionAmount = $agent->commission_type === 'percentage'
                ? round($payment->amount * ($agent->commission_rate / 100), 2)
                : $agent->commission_rate;

            $agent->increment('total_commission_earned', $commissionAmount);

            CommissionEntry::create([
                'tenant_id' => $tenantId,
                'agent_id' => $agent->id,
                'booking_id' => $payment->booking_id,
                'payment_id' => $payment->id,
                'type' => 'earned',
                'amount' => $commissionAmount,
                'balance_after' => $agent->fresh()->total_commission_earned - $agent->total_commission_paid,
                'notes' => "Auto-earned from payment #{$payment->id}",
            ]);
        }

        return response()->json([
            'message' => 'Payment recorded successfully',
            'data' => $payment,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $payment = Payment::where('tenant_id', $request->user->tenant_id)
            ->with('invoice', 'booking')
            ->findOrFail($id);

        return response()->json(['data' => $payment]);
    }

    public function update(Request $request, $id)
    {
        $payment = Payment::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,completed,failed,refunded',
            'notes' => 'nullable|string',
        ]);

        $payment->update($validated);

        if ($payment->invoice) {
            $payment->invoice->recalculatePaidAmount();
        }

        return response()->json(['message' => 'Payment updated', 'data' => $payment]);
    }

    public function getPaymentStats(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $stats = [
            'total_completed' => (float) Payment::where('tenant_id', $tenantId)->where('status', 'completed')->sum('amount'),
            'total_pending' => (float) Payment::where('tenant_id', $tenantId)->where('status', 'pending')->sum('amount'),
            'total_refunded' => (float) Payment::where('tenant_id', $tenantId)->where('status', 'refunded')->sum('amount'),
            'total_commission' => (float) Payment::where('tenant_id', $tenantId)->where('status', 'completed')->sum('commission_amount'),
            'by_method' => Payment::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->selectRaw('payment_method, count(*) as count, sum(amount) as total')
                ->groupBy('payment_method')
                ->get(),
        ];

        return response()->json(['data' => $stats]);
    }
}
