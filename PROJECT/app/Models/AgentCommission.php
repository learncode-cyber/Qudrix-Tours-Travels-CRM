<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCommission extends Model
{
    protected $fillable = [
        'tenant_id', 'agent_id', 'booking_id', 'booking_amount', 'commission_rate',
        'commission_amount', 'currency', 'status', 'agent_payout_id',
    ];

    protected $casts = [
        'booking_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }

    /**
     * Commission is always derived here, never accepted from a request —
     * so the amount cannot be tampered with by whoever creates the record.
     */
    public static function calculate(float $bookingAmount, float $commissionRate): float
    {
        return round($bookingAmount * ($commissionRate / 100), 2);
    }
}
