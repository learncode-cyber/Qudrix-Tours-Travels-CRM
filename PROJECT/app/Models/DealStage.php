<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealStage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'lead_id', 'stage', 'entered_at', 'exited_at',
        'duration_days', 'notes'
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'exited_at' => 'datetime',
    ];

    protected $dates = ['entered_at', 'exited_at'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function calculateDuration(): int
    {
        if (!$this->exited_at) {
            return now()->diffInDays($this->entered_at);
        }
        return $this->exited_at->diffInDays($this->entered_at);
    }
}
