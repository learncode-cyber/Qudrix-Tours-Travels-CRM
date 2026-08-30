<?php
namespace App\Services;

use App\Models\PricingCalculationLog;
use App\Models\PricingRule;

// Deterministic, rule-based pricing engine (Directive S7). Given a base
// cost and a context (travel date, group size, customer segment, booking
// lead time), applies every matching, active PricingRule in a fixed order
// and returns a fully itemized, reproducible breakdown. This is the ONLY
// place price adjustments are computed — no LLM ever sets a final price
// directly; an AI layer may only *recommend* a PricingRule for a human to
// approve (Directive S7/S8).
class PricingEngine
{
    public function calculate(int $tenantId, float $baseCost, array $context, ?int $userId = null): array
    {
        $rules = PricingRule::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (PricingRule $rule) => $rule->matches($context));

        $runningPrice = $baseCost;
        $appliedRules = [];

        foreach ($rules as $rule) {
            $amount = $rule->adjustment_type === 'percentage'
                ? round($runningPrice * ((float) $rule->adjustment_value / 100), 2)
                : (float) $rule->adjustment_value;

            $runningPrice = round($runningPrice + $amount, 2);

            $appliedRules[] = [
                'rule_id' => $rule->id,
                'name' => $rule->name,
                'factor' => $rule->factor,
                'adjustment_type' => $rule->adjustment_type,
                'adjustment_value' => (float) $rule->adjustment_value,
                'amount' => $amount,
                'price_after' => $runningPrice,
            ];
        }

        $log = PricingCalculationLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'base_cost' => $baseCost,
            'context' => $context,
            'applied_rules' => $appliedRules,
            'final_price' => $runningPrice,
            'created_at' => now(),
        ]);

        return [
            'base_cost' => $baseCost,
            'applied_rules' => $appliedRules,
            'final_price' => $runningPrice,
            'calculation_log_id' => $log->id,
        ];
    }
}
