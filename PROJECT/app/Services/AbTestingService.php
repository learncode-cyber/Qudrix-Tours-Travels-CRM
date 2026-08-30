<?php
namespace App\Services;

use App\Models\AbAssignment;
use App\Models\AbExperiment;
use App\Models\AbVariant;
use App\Models\Lead;

// Sales script A/B testing (Directive S14).
//
// Every reported figure — response rate, conversion, booking value,
// time-to-close — is computed from real recorded assignments and outcomes.
// Nothing is simulated, and the service refuses to report a "winner" on
// a sample too small to mean anything.
class AbTestingService
{
    // Below this many assignments per variant, a difference in rates is
    // almost certainly noise. The service reports the numbers but declines
    // to name a winner, rather than encouraging a decision on 3 leads.
    private const MIN_SAMPLE_PER_VARIANT = 30;

    /**
     * Deterministically assign a lead to a variant, once.
     *
     * Uses a hash of the experiment + lead id rather than rand(), so the
     * same lead always lands in the same variant even if this is called
     * again, and the split is reproducible.
     */
    public function assign(AbExperiment $experiment, Lead $lead, ?int $userId = null): ?AbAssignment
    {
        if (!$experiment->isRunning()) {
            return null;
        }

        $existing = AbAssignment::where('ab_experiment_id', $experiment->id)
            ->where('lead_id', $lead->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $variants = $experiment->variants()->where('is_active', true)->orderBy('id')->get();
        if ($variants->isEmpty()) {
            return null;
        }

        $variant = $this->pickWeighted($variants, $experiment->id . ':' . $lead->id);

        return AbAssignment::create([
            'tenant_id' => $experiment->tenant_id,
            'ab_experiment_id' => $experiment->id,
            'ab_variant_id' => $variant->id,
            'lead_id' => $lead->id,
            'assigned_to' => $userId,
        ]);
    }

    public function recordResponse(AbAssignment $assignment): AbAssignment
    {
        if (!$assignment->responded) {
            $assignment->update(['responded' => true, 'responded_at' => now()]);
        }

        return $assignment->fresh();
    }

    public function recordConversion(AbAssignment $assignment, ?float $bookingValue = null): AbAssignment
    {
        if (!$assignment->converted) {
            $assignment->update([
                'converted' => true,
                'converted_at' => now(),
                'booking_value' => $bookingValue,
                'time_to_close_hours' => (int) $assignment->created_at->diffInHours(now()),
                // A conversion implies a response even if none was logged.
                'responded' => true,
                'responded_at' => $assignment->responded_at ?? now(),
            ]);
        }

        return $assignment->fresh();
    }

    /**
     * Real results per variant (Directive S14: response rate, conversion,
     * booking value, time-to-close).
     */
    public function results(AbExperiment $experiment): array
    {
        $variants = $experiment->variants()->orderBy('label')->get();

        $rows = $variants->map(function (AbVariant $variant) {
            $assignments = AbAssignment::where('ab_variant_id', $variant->id);

            $total = (clone $assignments)->count();
            $responded = (clone $assignments)->where('responded', true)->count();
            $converted = (clone $assignments)->where('converted', true)->count();
            $value = (float) (clone $assignments)->sum('booking_value');
            $avgClose = (clone $assignments)->whereNotNull('time_to_close_hours')->avg('time_to_close_hours');

            return [
                'variant_id' => $variant->id,
                'label' => $variant->label,
                'assignments' => $total,
                'responded' => $responded,
                'response_rate_percent' => $total > 0 ? round(($responded / $total) * 100, 2) : null,
                'converted' => $converted,
                'conversion_rate_percent' => $total > 0 ? round(($converted / $total) * 100, 2) : null,
                'total_booking_value' => round($value, 2),
                'average_booking_value' => $converted > 0 ? round($value / $converted, 2) : null,
                'average_time_to_close_hours' => $avgClose !== null ? round((float) $avgClose, 1) : null,
            ];
        })->all();

        return [
            'experiment' => [
                'id' => $experiment->id,
                'name' => $experiment->name,
                'status' => $experiment->status,
                'hypothesis' => $experiment->hypothesis,
            ],
            'variants' => $rows,
            'winner' => $this->determineWinner($rows),
        ];
    }

    /**
     * Names a leading variant only when every variant has a usable sample.
     * Otherwise it says so plainly — an A/B tool that declares a winner on
     * five leads is worse than one that admits it does not know yet.
     */
    private function determineWinner(array $rows): array
    {
        if (count($rows) < 2) {
            return ['decided' => false, 'reason' => 'An experiment needs at least two variants.'];
        }

        $underpowered = array_filter($rows, fn ($r) => $r['assignments'] < self::MIN_SAMPLE_PER_VARIANT);
        if (!empty($underpowered)) {
            $labels = implode(', ', array_column($underpowered, 'label'));
            return [
                'decided' => false,
                'reason' => "Sample too small to call a winner (variant(s) {$labels} have fewer than "
                    . self::MIN_SAMPLE_PER_VARIANT . ' assignments). The rates below are real but not yet reliable.',
            ];
        }

        $ranked = $rows;
        usort($ranked, fn ($a, $b) => ($b['conversion_rate_percent'] ?? 0) <=> ($a['conversion_rate_percent'] ?? 0));

        $best = $ranked[0];
        $second = $ranked[1];
        $margin = ($best['conversion_rate_percent'] ?? 0) - ($second['conversion_rate_percent'] ?? 0);

        if ($margin < 1.0) {
            return [
                'decided' => false,
                'reason' => 'No meaningful difference between the top variants (under 1 percentage point).',
            ];
        }

        return [
            'decided' => true,
            'variant_label' => $best['label'],
            'conversion_rate_percent' => $best['conversion_rate_percent'],
            'margin_over_next_percent' => round($margin, 2),
            'note' => 'Leading variant by observed conversion rate. This is a descriptive result, '
                . 'not a statistical significance test.',
        ];
    }

    /**
     * Weighted, deterministic pick from a stable hash of the seed.
     */
    private function pickWeighted($variants, string $seed): AbVariant
    {
        $totalWeight = max(1, (int) $variants->sum('weight'));
        $point = hexdec(substr(md5($seed), 0, 8)) % $totalWeight;

        $cursor = 0;
        foreach ($variants as $variant) {
            $cursor += max(1, (int) $variant->weight);
            if ($point < $cursor) {
                return $variant;
            }
        }

        return $variants->last();
    }
}
