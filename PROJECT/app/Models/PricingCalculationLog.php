<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingCalculationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'user_id', 'base_cost', 'context', 'applied_rules',
        'final_price', 'created_at',
    ];

    protected $casts = [
        'base_cost' => 'decimal:2',
        'context' => 'json',
        'applied_rules' => 'json',
        'final_price' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
