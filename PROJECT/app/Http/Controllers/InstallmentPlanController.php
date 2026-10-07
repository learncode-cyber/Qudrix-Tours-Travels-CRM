<?php
namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CommissionEntry;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstallmentPlanController extends Controller
{
    public function indexForBooking(Request $request, $bookingId)
    {
        $plans = InstallmentPlan::where('tenant_id', $request->user->tenant_id)
            ->where('booking_id', $bookingId)
            ->with('installments')
            ->get();

        return response()->json(['data' => $plans]);
    }

    /**
     * Creates a plan and generates its individual installments split
     * evenly (remainder absorbed into the final installment so the sum
     * always equals total_amount exactly — avoids float rounding leaving
     * a few cents unaccounted for).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'total_amount' => 'required|numeric|min:0.01',
            'number_of_installments' => 'required|integer|min:2|max:60',
            'frequency' => 'sometimes|in:monthly,custom',
            'first_due_date' => 'required|date',
        ]);

        $booking = Booking::where('tenant_id', $request->user->tenant_id)->findOrFail($validated['booking_id']);

        $plan = DB::transaction(function () use ($validated, $request, $booking) {
            $plan = InstallmentPlan::create([
                'tenant_id' => $request->user->tenant_id,
                'booking_id' => $booking->id,
                'invoice_id' => $validated['invoice_id'] ?? null,
                'total_amount' => $validated['total_amount'],
                'number_of_installments' => $validated['number_of_installments'],
                'frequency' => $validated['frequency'] ?? 'monthly',
                'status' => 'active',
                'created_by' => $request->user->id,
            ]);

            $n = $validated['number_of_installments'];
            $baseAmount = round($validated['total_amount'] / $n, 2);
            $dueDate = \Carbon\Carbon::parse($validated['first_due_date']);

            $runningTotal = 0;
            for ($i = 1; $i <= $n; $i++) {
                $isLast = $i === $n;
                $amount = $isLast ? round($validated['total_amount'] - $runningTotal, 2) : $baseAmount;
                $runningTotal += $amount;

                Installment::create([
                    'tenant_id' => $request->user->tenant_id,
                    'installment_plan_id' => $plan->id,
                    'sequence_number' => $i,
                    'due_date' => $dueDate->copy()->addMonthsNoOverflow($i - 1),
                    'amount' => $amount,
                    'status' => 'pending',
                ]);
            }

            return $plan;
        });

        return response()->json(['data' => $plan->load('installments')], 201);
    }

    /**
     * Records a payment against one installment. Creates the Payment row
     * (reusing the existing Payment model/table rather than a parallel
     * ledger) and links it, then marks the installment paid.
     */
    public function recordPayment(Request $request, $installmentId)
    {
        $installment = Installment::where('tenant_id', $request->user->tenant_id)->findOrFail($installmentId);

        if ($installment->status === 'paid') {
            return response()->json(['error' => 'Installment already paid'], 422);
        }

        $validated = $request->validate([
            'payment_method' => 'required|string',
            'transaction_id' => 'nullable|string',
            'reference_number' => 'nullable|string',
        ]);

        DB::transaction(function () use ($installment, $validated, $request) {
            $plan = $installment->plan;

            $payment = Payment::create([
                'tenant_id' => $request->user->tenant_id,
                'booking_id' => $plan->booking_id,
                'invoice_id' => $plan->invoice_id,
                'amount' => $installment->amount,
                'payment_method' => $validated['payment_method'],
                'transaction_id' => $validated['transaction_id'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'status' => 'completed',
                'paid_at' => now(),
            ]);

            $installment->update(['status' => 'paid', 'payment_id' => $payment->id, 'paid_at' => now()]);

            if ($plan->installments()->where('status', '!=', 'paid')->doesntExist()) {
                $plan->update(['status' => 'completed']);
            }

            // MASTER_PROJECT_AUDIT.md P2: same auto-earn hook as
            // PaymentController@store — installment payments create a
            // Payment row directly here rather than going through that
            // controller, so the commission-earning logic must be
            // duplicated (not skipped) for this path too.
            $booking = $plan->booking;
            if ($booking?->agent_id) {
                $agent = $booking->agent;
                $commissionAmount = $agent->commission_type === 'percentage'
                    ? round($payment->amount * ($agent->commission_rate / 100), 2)
                    : $agent->commission_rate;

                $agent->increment('total_commission_earned', $commissionAmount);

                CommissionEntry::create([
                    'tenant_id' => $request->user->tenant_id,
                    'agent_id' => $agent->id,
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id,
                    'type' => 'earned',
                    'amount' => $commissionAmount,
                    'balance_after' => $agent->fresh()->total_commission_earned - $agent->total_commission_paid,
                    'notes' => "Auto-earned from installment payment #{$payment->id}",
                ]);
            }
        });

        return response()->json(['data' => $installment->fresh('payment')]);
    }

    /**
     * Marks pending installments whose due_date has passed as overdue.
     * Also invoked by the scheduled console command
     * (app/Console/Commands/FlagOverdueInstallments.php) — exposed here
     * too as a manually-triggerable endpoint.
     */
    public function flagOverdue(Request $request)
    {
        $count = Installment::where('tenant_id', $request->user->tenant_id)
            ->where('status', 'pending')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        return response()->json(['message' => "{$count} installment(s) flagged overdue"]);
    }
}
