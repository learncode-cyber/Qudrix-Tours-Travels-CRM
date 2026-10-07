<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Get current subscription for the tenant.
     */
    public function current(Request $request)
    {
        $sub = Subscription::where('tenant_id', $request->user->tenant_id)
            ->with('plan')
            ->first();

        if (!$sub) {
            return response()->json(['data' => null, 'message' => 'No active subscription']);
        }

        return response()->json(['data' => $sub]);
    }

    /**
     * Create a new subscription (in real system, this would integrate with payment gateway).
     * For now, this is a placeholder that just creates the subscription record.
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        // Verify the plan belongs to caller's tenant
        $plan = SubscriptionPlan::where('tenant_id', $request->user->tenant_id)
            ->findOrFail($validated['plan_id']);

        // Cancel any existing subscription
        Subscription::where('tenant_id', $request->user->tenant_id)
            ->whereIn('status', ['active', 'past_due', 'paused'])
            ->update(['status' => 'canceled', 'canceled_at' => now()]);

        // Create new subscription
        $sub = Subscription::create([
            'tenant_id' => $request->user->tenant_id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => $validated['billing_cycle'],
            'current_period_start' => now(),
            'current_period_end' => now()->addMonths($validated['billing_cycle'] === 'yearly' ? 12 : 1),
            'renewal_date' => now()->addMonths($validated['billing_cycle'] === 'yearly' ? 12 : 1),
            'next_billing_amount' => $validated['billing_cycle'] === 'yearly' ? $plan->price_yearly : $plan->price_monthly,
        ]);

        return response()->json(['data' => $sub, 'message' => 'Subscription created. (Payment integration not implemented)'], 201);
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request)
    {
        $sub = Subscription::where('tenant_id', $request->user->tenant_id)
            ->where('status', '!=', 'canceled')
            ->firstOrFail();

        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
            'effective_date' => 'nullable|date|after:today',
        ]);

        if (!empty($validated['effective_date'])) {
            // Cancel at end of period
            $sub->update([
                'cancel_at' => $validated['effective_date'],
                'cancellation_reason' => $validated['cancellation_reason'],
            ]);
        } else {
            // Cancel immediately
            $sub->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'cancellation_reason' => $validated['cancellation_reason'],
            ]);
        }

        return response()->json(['data' => $sub, 'message' => 'Subscription cancelled']);
    }

    /**
     * Get usage metrics for current billing period
     */
    public function usage(Request $request)
    {
        $sub = Subscription::where('tenant_id', $request->user->tenant_id)->first();

        if (!$sub) {
            return response()->json(['data' => null]);
        }

        $logs = $sub->usageLogs()
            ->where('reset_date', '>=', $sub->current_period_start)
            ->where('reset_date', '<', $sub->current_period_end)
            ->groupBy('metric_key')
            ->selectRaw('metric_key, SUM(value) as total')
            ->pluck('total', 'metric_key');

        return response()->json([
            'data' => [
                'subscription_id' => $sub->id,
                'period_start' => $sub->current_period_start,
                'period_end' => $sub->current_period_end,
                'usage' => $logs,
                'plan_limits' => [
                    'max_users' => $sub->plan->max_users,
                    'max_api_calls_per_day' => $sub->plan->max_api_calls_per_day,
                    'max_contacts' => $sub->plan->max_contacts,
                ],
            ],
        ]);
    }
}
