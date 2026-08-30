<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    public const STATUSES = ['present', 'absent', 'late', 'half_day', 'leave', 'holiday'];

    protected $fillable = [
        'tenant_id', 'employee_id', 'work_date', 'check_in_at', 'check_out_at', 'status', 'note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }

    public function hoursWorked(): ?float
    {
        if (!$this->check_in_at || !$this->check_out_at) {
            return null;
        }
        return round($this->check_in_at->floatDiffInHours($this->check_out_at), 2);
    }
}
