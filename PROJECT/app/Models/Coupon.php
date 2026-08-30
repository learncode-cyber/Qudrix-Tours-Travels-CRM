<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'tenant_id', 'code', 'discount_type', 'discount_value', 'currency',
        'min_booking_amount', 'usage_limit', 'used_count',
        'valid_from', 'valid_until', 'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_booking_amount' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function redemptions(): HasMany { return $this->hasMany(CouponRedemption::class); }

    /**
     * Every reason a coupon can be rejected, checked server-side.
     * Returns null when valid, or the reason string when not.
     */
    public function rejectionReasonFor(float $bookingAmount): ?string
    {
        if (!$this->is_active) {
            return 'This coupon is not active.';
        }
        if ($this->valid_from && today()->lt($this->valid_from)) {
            return 'This coupon is not valid yet.';
        }
        if ($this->valid_until && today()->gt($this->valid_until)) {
            return 'This coupon has expired.';
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return 'This coupon has reached its usage limit.';
        }
        if ($this->min_booking_amount !== null && $bookingAmount < (float) $this->min_booking_amount) {
            return 'Booking amount is below this coupon\'s minimum.';
        }

        return null;
    }

    /** Discount is always derived here, never taken from a request. */
    public function discountFor(float $bookingAmount): float
    {
        $discount = $this->discount_type === 'percentage'
            ? $bookingAmount * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        // A discount can never exceed the booking itself.
        return round(min($discount, $bookingAmount), 2);
    }
}
