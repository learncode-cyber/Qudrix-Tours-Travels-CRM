<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// B2B reseller agent (Directive S3.L). Distinct from Vendor (a supplier the
// agency BUYS from) — an Agent SELLS on the agency's behalf and earns
// commission.
class Agent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'user_id', 'agency_name', 'contact_person', 'email', 'phone',
        'address', 'country', 'agent_code', 'commission_rate', 'balance',
        'status', 'kyc_status', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'balance' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function commissions(): HasMany { return $this->hasMany(AgentCommission::class); }
    public function payouts(): HasMany { return $this->hasMany(AgentPayout::class); }
    public function bookings(): HasMany { return $this->hasMany(Booking::class); }
    public function leads(): HasMany { return $this->hasMany(Lead::class); }

    public function canTransact(): bool
    {
        return $this->status === 'approved';
    }

    /** Commission earned and approved but not yet paid out. */
    public function unpaidCommission(): float
    {
        return (float) $this->commissions()->where('status', 'approved')->sum('commission_amount');
    }
}
