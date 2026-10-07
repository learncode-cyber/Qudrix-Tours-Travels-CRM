<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\AsJson;

class Quotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'lead_id', 'customer_id', 'created_by', 'package_id',
        'quotation_number', 'subject', 'description',
        'status', 'subtotal', 'tax_amount', 'discount_amount',
        'total_amount', 'currency', 'valid_until', 'notes',
        'payment_terms', 'travel_date', 'number_of_travelers',
        'special_requirements', 'quoted_budget', 'version',
        'parent_quotation_id', 'approval_status',
    ];

    // FIX (Phase 3 audit): 'terms' was previously fillable with an
    // AsJson cast, but no `terms` column was ever created in any
    // migration — only `payment_terms`. Assigning it would have thrown
    // "Unknown column 'terms'". Removed; payment_terms is the real field.
    protected $casts = [
        'valid_until' => 'datetime',
        'travel_date' => 'datetime',
        'quoted_budget' => 'decimal:2',
        'payment_terms' => AsJson::class,
    ];

    protected $dates = ['valid_until', 'travel_date'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function parentQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'parent_quotation_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Quotation::class, 'parent_quotation_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function calculateTotals(): void
    {
        $subtotal = $this->items()->sum('total');
        $this->update([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal + $this->tax_amount - $this->discount_amount
        ]);
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until < now();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }
}
