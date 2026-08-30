<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMemory extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'budget', 'travel_preference', 'destination', 'group_size',
        'previous_trip', 'preferred_channel', 'objection', 'requirement',
    ];

    protected $fillable = [
        'tenant_id', 'customer_id', 'lead_id', 'category', 'key', 'value',
        'source', 'confidence', 'is_sensitive', 'created_by',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'is_sensitive' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    // Entries a human marked sensitive are withheld from AI prompts by
    // default, so the model is never fed personal data it does not need.
    public function scopeSafeForAi($query)
    {
        return $query->where('is_sensitive', false);
    }
}
