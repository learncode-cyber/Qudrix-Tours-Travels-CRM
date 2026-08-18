<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'assigned_to', 'title', 'description', 'type',
        'status', 'priority', 'related_entity_type', 'related_entity_id',
        'due_date', 'completed_at'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $dates = ['due_date', 'completed_at'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->nullable();
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date < now() && !$this->isCompleted();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed' || $this->completed_at !== null;
    }

    public function markComplete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    public function markIncomplete(): void
    {
        $this->update([
            'status' => 'open',
            'completed_at' => null
        ]);
    }
}
