<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldDefinition extends Model
{
    protected $fillable = [
        'tenant_id', 'entity_type', 'key', 'label', 'field_type', 'options',
        'is_required',
    ];

    protected $casts = [
        'options' => 'json',
        'is_required' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
