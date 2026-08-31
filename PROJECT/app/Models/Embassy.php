<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Embassy extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'country', 'city', 'address', 'contact_email',
        'contact_phone', 'website', 'average_processing_days', 'notes',
    ];

    protected $casts = [
        'average_processing_days' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function visaApplications(): HasMany
    {
        return $this->hasMany(VisaApplication::class);
    }
}
