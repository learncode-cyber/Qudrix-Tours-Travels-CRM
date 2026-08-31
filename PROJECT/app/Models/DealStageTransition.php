<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealStageTransition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'deal_id', 'stage', 'entered_at', 'exited_at', 'duration_days',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'exited_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function calculateDuration(): int
    {
        if (!$this->exited_at) {
            return now()->diffInDays($this->entered_at);
        }
        return $this->exited_at->diffInDays($this->entered_at);
    }
}
