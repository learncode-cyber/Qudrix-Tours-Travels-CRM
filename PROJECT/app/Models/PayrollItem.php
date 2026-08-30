<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'basic_salary', 'allowances',
        'deductions', 'net_pay', 'days_present', 'days_absent', 'note',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    public function run(): BelongsTo { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
