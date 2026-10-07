<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PricingRule;
use Carbon\Carbon;

/**
 * PHASE 6: Pricing Engine.
 *
 * Deliberately deterministic — same inputs always produce the same
 * output, and every adjustment applied is returned in the response
 * breakdown so a human can audit exactly why a price came out the way
 * it did. This is a hard requirement from the phase spec: "Final
 * pricing rules must remain deterministic and auditable. Never allow AI
 * alone to secretly change financial values." No AI call happens
 * anywhere in this class.
 */
class PricingEngineService
{
    /**
     * @param Package $package
     * @param array{
     *   travel_date?: string,
     *   number_of_travelers?: int,
     *   customer_segment_id?: int,
     *   destination?: string,
     * } $params
     */
    public function calculate(Package $package, array $params): array
    {
        $basePrice = (float) $package->base_price;
        $travelers = $params['number_of_travelers'] ?? 1;

        // Step 1: markup over supplier cost, if configured — otherwise
        // base_price is treated as already being the sell price.
        $markupAmount = 0;
        if ($package->supplier_cost !== null && $package->markup_percentage !== null) {
            $markupAmount = round((float) $package->supplier_cost * ((float) $package->markup_percentage / 100), 2);
            $basePrice = (float) $package->supplier_cost + $markupAmount;
        }

        $perPersonPrice = $basePrice;
        $subtotal = round($perPersonPrice * $travelers, 2);

        $rules = PricingRule::where('tenant_id', $package->tenant_id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        $applied = [];
        $runningTotal = $subtotal;

        foreach ($rules as $rule) {
            $matches = $this->ruleMatches($rule, $params, $travelers);
            if (!$matches) {
                continue;
            }

            $adjustment = $rule->adjustment_type === 'percentage'
                ? round($runningTotal * ((float) $rule->adjustment_value / 100), 2)
                : (float) $rule->adjustment_value;

            $runningTotal = round($runningTotal + $adjustment, 2);

            $applied[] = [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'rule_type' => $rule->rule_type,
                'adjustment_type' => $rule->adjustment_type,
                'adjustment_value' => (float) $rule->adjustment_value,
                'amount_applied' => $adjustment,
                'running_total_after' => $runningTotal,
            ];
        }

        $totalCost = $package->supplier_cost !== null
            ? round((float) $package->supplier_cost * $travelers, 2)
            : null;

        $margin = $totalCost !== null ? round($runningTotal - $totalCost, 2) : null;
        $marginPercentage = ($margin !== null && $runningTotal > 0)
            ? round(($margin / $runningTotal) * 100, 2)
            : null;

        return [
            'package_id' => $package->id,
            'package_name' => $package->name,
            'currency' => $package->currency ?? 'USD',
            'number_of_travelers' => $travelers,
            'per_person_base_price' => round($perPersonPrice, 2),
            'markup_amount_per_person' => $markupAmount,
            'subtotal' => $subtotal,
            'rules_applied' => $applied,
            'final_price' => $runningTotal,
            'price_per_person' => $travelers > 0 ? round($runningTotal / $travelers, 2) : $runningTotal,
            // Cost/margin are internal figures — callers exposing this to a
            // customer-facing surface must strip these two fields.
            'internal' => [
                'total_supplier_cost' => $totalCost,
                'margin' => $margin,
                'margin_percentage' => $marginPercentage,
            ],
        ];
    }

    protected function ruleMatches(PricingRule $rule, array $params, int $travelers): bool
    {
        $c = $rule->conditions ?? [];

        return match ($rule->rule_type) {
            'season' => $this->matchesSeason($c, $params),
            'group_size' => $this->matchesGroupSize($c, $travelers),
            'booking_timing' => $this->matchesBookingTiming($c, $params),
            'customer_segment' => isset($c['segment_id']) && ($params['customer_segment_id'] ?? null) == $c['segment_id'],
            'demand' => isset($c['destination']) && ($params['destination'] ?? null) === $c['destination'],
            default => false,
        };
    }

    protected function matchesSeason(array $c, array $params): bool
    {
        if (empty($params['travel_date']) || !isset($c['start_month']) || !isset($c['end_month'])) {
            return false;
        }

        $month = Carbon::parse($params['travel_date'])->month;

        if ($c['start_month'] <= $c['end_month']) {
            return $month >= $c['start_month'] && $month <= $c['end_month'];
        }

        // wraps year end, e.g. Nov(11) - Feb(2)
        return $month >= $c['start_month'] || $month <= $c['end_month'];
    }

    protected function matchesGroupSize(array $c, int $travelers): bool
    {
        if (isset($c['min_travelers']) && $travelers < $c['min_travelers']) {
            return false;
        }
        if (isset($c['max_travelers']) && $travelers > $c['max_travelers']) {
            return false;
        }
        return isset($c['min_travelers']) || isset($c['max_travelers']);
    }

    protected function matchesBookingTiming(array $c, array $params): bool
    {
        if (empty($params['travel_date'])) {
            return false;
        }

        $daysUntilTravel = now()->diffInDays(Carbon::parse($params['travel_date']), false);

        if (isset($c['days_before_travel_min']) && $daysUntilTravel < $c['days_before_travel_min']) {
            return false;
        }
        if (isset($c['days_before_travel_max']) && $daysUntilTravel > $c['days_before_travel_max']) {
            return false;
        }
        return isset($c['days_before_travel_min']) || isset($c['days_before_travel_max']);
    }
}
