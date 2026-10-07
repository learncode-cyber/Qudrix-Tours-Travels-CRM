<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\AsJson;

class PricingRule extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'rule_type', 'conditions',
        'adjustment_type', 'adjustment_value', 'priority', 'is_active',
    ];

    protected $casts = [
        'conditions' => AsJson::class,
        'adjustment_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
