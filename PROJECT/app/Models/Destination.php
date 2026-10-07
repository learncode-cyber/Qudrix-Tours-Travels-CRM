<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Destination extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'country', 'city', 'region', 'description',
        'latitude', 'longitude', 'tourist_season', 'weather_info',
        'visa_required', 'currency', 'language', 'image_url'
    ];

    protected $casts = [
        'visa_required' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
