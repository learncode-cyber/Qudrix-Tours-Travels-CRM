<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'coupon_id', 'booking_id', 'customer_id', 'discount_applied', 'created_at',
    ];

    protected $casts = ['discount_applied' => 'decimal:2', 'created_at' => 'datetime'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
}
