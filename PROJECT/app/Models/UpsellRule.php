<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpsellRule extends Model
{
    public const TRIGGER_TYPES = ['flight', 'hotel', 'tour', 'visa', 'hajj', 'umrah', 'transport', 'any'];
    public const RECOMMEND_TYPES = ['hotel', 'flight', 'visa', 'insurance', 'transport', 'tour_guide', 'addon'];

    protected $fillable = [
        'tenant_id', 'name', 'trigger_type', 'recommend_type', 'description',
        'suggested_price', 'currency', 'priority', 'requires_availability_check', 'is_active',
    ];

    protected $casts = [
        'suggested_price' => 'decimal:2',
        'requires_availability_check' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
