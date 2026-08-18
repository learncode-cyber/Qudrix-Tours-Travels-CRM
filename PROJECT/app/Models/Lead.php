<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'branch_id', 'assigned_to', 'name',
        'email', 'phone', 'company', 'designation',
        'source', 'status', 'priority', 'notes',
        'last_contacted_at', 'follow_up_date', 'estimated_value',
        'conversion_probability'
    ];

    protected $casts = [
        'last_contacted_at' => 'datetime',
        'follow_up_date' => 'datetime',
        'estimated_value' => 'decimal:2',
    ];

    protected $dates = ['last_contacted_at', 'follow_up_date'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->nullable();
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->nullable();
    }

    public function isQualified(): bool
    {
        return $this->status === 'qualified';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' || $this->status === 'new';
    }
}
