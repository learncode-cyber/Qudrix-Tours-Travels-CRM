<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbAssignment extends Model
{
    protected $fillable = [
        'tenant_id', 'ab_experiment_id', 'ab_variant_id', 'lead_id', 'assigned_to',
        'responded', 'converted', 'booking_value', 'time_to_close_hours',
        'responded_at', 'converted_at',
    ];

    protected $casts = [
        'responded' => 'boolean',
        'converted' => 'boolean',
        'booking_value' => 'decimal:2',
        'responded_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function experiment(): BelongsTo { return $this->belongsTo(AbExperiment::class, 'ab_experiment_id'); }
    public function variant(): BelongsTo { return $this->belongsTo(AbVariant::class, 'ab_variant_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
}
