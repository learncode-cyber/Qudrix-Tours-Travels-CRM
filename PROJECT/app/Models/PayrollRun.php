<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'tenant_id', 'period', 'status', 'total_gross', 'total_deductions',
        'total_net', 'currency', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'total_gross' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function items(): HasMany { return $this->hasMany(PayrollItem::class); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    /** Recomputes the run totals from its real line items. */
    public function recalculateTotals(): void
    {
        $this->update([
            'total_gross' => $this->items()->sum(\Illuminate\Support\Facades\DB::raw('basic_salary + allowances')),
            'total_deductions' => $this->items()->sum('deductions'),
            'total_net' => $this->items()->sum('net_pay'),
        ]);
    }
}
