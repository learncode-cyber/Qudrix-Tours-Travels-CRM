<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\AsJson;

class Package extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'type', 'description',
        'days', 'nights', 'destination', 'base_price',
        'supplier_cost', 'markup_percentage', 'currency',
        'inclusions', 'exclusions', 'is_active', 'status'
    ];

    protected $casts = [
        'inclusions' => AsJson::class,
        'exclusions' => AsJson::class,
        'is_active' => 'boolean',
        'base_price' => 'decimal:2',
        'supplier_cost' => 'decimal:2',
        'markup_percentage' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
