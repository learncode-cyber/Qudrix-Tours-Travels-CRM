<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayment extends Model
{
    protected $fillable = [
        'tenant_id', 'vendor_id', 'amount', 'currency', 'status',
        'due_date', 'paid_date', 'reference', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'date',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
}
