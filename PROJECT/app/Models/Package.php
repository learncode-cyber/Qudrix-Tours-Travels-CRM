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
        'inclusions', 'exclusions', 'is_active', 'status',
        'is_custom_built', 'components', 'built_by', 'built_for_customer_id',
    ];

    protected $casts = [
        'inclusions' => AsJson::class,
        'exclusions' => AsJson::class,
        'is_active' => 'boolean',
        'base_price' => 'decimal:2',
        'is_custom_built' => 'boolean',
        'components' => 'json',
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
