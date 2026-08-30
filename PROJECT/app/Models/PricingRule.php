<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'factor', 'season_start', 'season_end',
        'min_group_size', 'max_group_size',
        'booking_days_before_travel_min', 'booking_days_before_travel_max',
        'customer_segment_id', 'adjustment_type', 'adjustment_value',
        'priority', 'is_active',
    ];

    protected $casts = [
        'season_start' => 'date',
        'season_end' => 'date',
        'adjustment_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customerSegment(): BelongsTo { return $this->belongsTo(CustomerSegment::class); }

    // Whether this rule applies to the given calculation context. Every
    // condition present on the rule must match — an unset condition is
    // treated as "no constraint on this factor".
    public function matches(array $context): bool
    {
        if ($this->season_start && $this->season_end) {
            $travelDate = $context['travel_date'] ?? null;
            if (!$travelDate) {
                return false;
            }
            $travelDate = \Illuminate\Support\Carbon::parse($travelDate);
            if ($travelDate->lt($this->season_start) || $travelDate->gt($this->season_end)) {
                return false;
            }
        }

        if ($this->min_group_size !== null || $this->max_group_size !== null) {
            $groupSize = $context['group_size'] ?? null;
            if ($groupSize === null) {
                return false;
            }
            if ($this->min_group_size !== null && $groupSize < $this->min_group_size) {
                return false;
            }
            if ($this->max_group_size !== null && $groupSize > $this->max_group_size) {
                return false;
            }
        }

        if ($this->booking_days_before_travel_min !== null || $this->booking_days_before_travel_max !== null) {
            $daysBefore = $context['booking_days_before_travel'] ?? null;
            if ($daysBefore === null) {
                return false;
            }
            if ($this->booking_days_before_travel_min !== null && $daysBefore < $this->booking_days_before_travel_min) {
                return false;
            }
            if ($this->booking_days_before_travel_max !== null && $daysBefore > $this->booking_days_before_travel_max) {
                return false;
            }
        }

        if ($this->customer_segment_id !== null && ($context['customer_segment_id'] ?? null) !== $this->customer_segment_id) {
            return false;
        }

        return true;
    }
}
