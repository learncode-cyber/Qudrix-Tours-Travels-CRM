<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadScore extends Model
{
    protected $fillable = [
        'tenant_id', 'lead_id', 'score', 'score_type', 'description', 'metadata'
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    public $timestamps = false;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
