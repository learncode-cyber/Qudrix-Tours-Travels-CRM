<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpsellOffer extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'category', 'applies_to_destination',
        'applies_to_booking_type', 'price', 'currency', 'description', 'is_active',
    ];
    protected $casts = ['is_active' => 'boolean', 'price' => 'decimal:2'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
