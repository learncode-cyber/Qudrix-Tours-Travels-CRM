<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpsellRecommendation extends Model
{
    protected $fillable = [
        'tenant_id', 'upsell_rule_id', 'booking_id', 'lead_id', 'shown_by',
        'recommend_type', 'outcome', 'accepted_value', 'responded_at',
    ];

    protected $casts = [
        'accepted_value' => 'decimal:2',
        'responded_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function rule(): BelongsTo { return $this->belongsTo(UpsellRule::class, 'upsell_rule_id'); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
}
