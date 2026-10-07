<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'booking_id', 'category', 'description', 'amount', 'currency', 'expense_date', 'paid_by', 'notes'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
}
