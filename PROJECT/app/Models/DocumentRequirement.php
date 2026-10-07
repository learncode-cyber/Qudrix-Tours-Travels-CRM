<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentRequirement extends Model
{
    protected $fillable = ['tenant_id', 'entity_type', 'name', 'is_mandatory', 'sort_order'];

    protected $casts = ['is_mandatory' => 'boolean'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(DocumentSubmission::class);
    }
}
