<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationTemplate extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'subject', 'description', 'default_items',
        'default_payment_terms', 'default_validity_days', 'is_active',
    ];

    protected $casts = [
        'default_items' => 'json',
        'default_payment_terms' => 'json',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
