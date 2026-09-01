<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'quotation_id', 'package_id', 'description',
        'quantity', 'unit_price', 'tax_rate', 'discount', 'total'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function calculateTotal(): void
    {
        $itemTotal = ($this->quantity * $this->unit_price) - $this->discount;
        $tax = $itemTotal * ($this->tax_rate / 100);
        $this->update(['total' => $itemTotal + $tax]);
    }
}
