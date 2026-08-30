<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'customer_id', 'booking_id', 'quotation_id', 'created_by',
        'invoice_number', 'status', 'subtotal', 'tax_amount', 'discount_amount',
        'total_amount', 'paid_amount', 'currency', 'issue_date', 'due_date', 'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }

    public function balanceDue(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 2);
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->balanceDue() > 0;
    }

    public function recalculateStatus(): void
    {
        $balance = $this->balanceDue();
        if ($balance <= 0) {
            $status = 'paid';
        } elseif ((float) $this->paid_amount > 0) {
            $status = 'partially_paid';
        } elseif ($this->isOverdue()) {
            $status = 'overdue';
        } else {
            $status = $this->status === 'draft' ? 'draft' : 'sent';
        }
        $this->update(['status' => $status]);
    }
}
