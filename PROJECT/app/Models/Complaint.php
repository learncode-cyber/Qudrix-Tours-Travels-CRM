<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'tenant_id', 'booking_id', 'customer_id', 'title', 'description',
        'category', 'priority', 'status', 'assigned_to', 'resolution',
        'resolution_date', 'sla_deadline', 'sla_breached', 'escalated_at',
        'involves_compensation', 'approval_status', 'compensation_amount',
    ];
    protected $casts = [
        'resolution_date' => 'datetime',
        'sla_deadline' => 'datetime',
        'escalated_at' => 'datetime',
        'sla_breached' => 'boolean',
        'involves_compensation' => 'boolean',
        'compensation_amount' => 'decimal:2',
    ];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function assignedStaff(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }

    public function isSlaBreached(): bool
    {
        return $this->sla_deadline
            && $this->sla_deadline->isPast()
            && !in_array($this->status, ['resolved', 'closed']);
    }
}
