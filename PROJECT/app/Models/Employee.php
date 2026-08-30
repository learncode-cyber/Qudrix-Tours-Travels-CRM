<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// HRM foundation model. Payroll/attendance/leave tables are added in the
// dedicated HRM phase; this establishes the employee record that those
// tables and the Task/CRM assignment features join against.
class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'user_id', 'employee_code', 'department', 'designation',
        'reporting_to', 'branch_id', 'joining_date', 'employment_status',
        'phone', 'address',
    ];

    protected $casts = [
        'joining_date' => 'date',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function manager(): BelongsTo { return $this->belongsTo(Employee::class, 'reporting_to'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
}
