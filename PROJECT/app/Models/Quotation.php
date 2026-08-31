<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'lead_id', 'customer_id', 'created_by',
        'quotation_number', 'subject', 'description',
        'status', 'source', 'subtotal', 'tax_amount', 'discount_amount',
        'total_amount', 'currency', 'valid_until', 'notes',
        'payment_terms', 'share_token', 'requires_approval',
        'approved_by', 'approved_at', 'quotation_template_id', 'version',
    ];

    protected $casts = [
        'valid_until' => 'datetime',
        'payment_terms' => 'array',
        'requires_approval' => 'boolean',
        'approved_at' => 'datetime',
    ];

    protected $dates = ['valid_until'];

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

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuotationTemplate::class, 'quotation_template_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'supersedes_quotation_id');
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
