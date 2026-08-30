<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'quotation_id', 'package_id', 'description',
        'quantity', 'unit_price', 'cost_price', 'markup_percentage',
        'tax_rate', 'discount', 'total'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'markup_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // When cost_price + markup_percentage are supplied instead of a direct
    // sell price, derive unit_price deterministically — never let the sell
    // price be guessed or left implicit.
    public static function priceFromMarkup(float $costPrice, float $markupPercentage): float
    {
        return round($costPrice * (1 + $markupPercentage / 100), 2);
    }

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
