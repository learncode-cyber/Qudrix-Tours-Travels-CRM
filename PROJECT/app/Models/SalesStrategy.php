<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesStrategy extends Model
{
    // The methodologies named in the directive. The prompt guidance for
    // each is admin-editable, so a tenant can tune wording without code.
    public const KEYS = [
        'consultative', 'spin', 'solution', 'value', 'relationship', 'challenger', 'sandler',
    ];

    protected $fillable = [
        'tenant_id', 'key', 'name', 'description', 'prompt_guidance', 'tone',
        'priority', 'is_active', 'customer_segment_id',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customerSegment(): BelongsTo { return $this->belongsTo(CustomerSegment::class); }
}
