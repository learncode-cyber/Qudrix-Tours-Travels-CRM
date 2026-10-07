<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversionEvent extends Model
{
    protected $fillable = [
        'tenant_id', 'lead_id', 'customer_id', 'event_name', 'event_value', 'currency',
        'meta_status', 'meta_sent_at', 'meta_response',
        'ga4_status', 'ga4_sent_at', 'ga4_response',
    ];

    protected $casts = [
        'event_value' => 'decimal:2',
        'meta_sent_at' => 'datetime',
        'ga4_sent_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
