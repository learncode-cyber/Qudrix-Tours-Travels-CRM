<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'invoice_id', 'description', 'quantity', 'unit_price', 'tax_rate', 'discount', 'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
