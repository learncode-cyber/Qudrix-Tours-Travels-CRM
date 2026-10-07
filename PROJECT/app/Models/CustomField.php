<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\AsJson;

class CustomField extends Model
{
    protected $fillable = [
        'tenant_id', 'entity_type', 'name', 'label',
        'field_type', 'options', 'is_required', 'sort_order',
    ];

    protected $casts = [
        'options' => AsJson::class,
        'is_required' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
