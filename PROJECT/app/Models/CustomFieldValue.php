<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldValue extends Model
{
    protected $fillable = [
        'tenant_id', 'custom_field_definition_id', 'entity_type', 'entity_id', 'value',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function definition(): BelongsTo { return $this->belongsTo(CustomFieldDefinition::class, 'custom_field_definition_id'); }
    public function entity() { return $this->morphTo(); }
}
