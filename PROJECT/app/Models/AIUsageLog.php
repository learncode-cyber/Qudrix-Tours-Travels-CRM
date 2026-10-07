<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIUsageLog extends Model
{
    protected $table = 'ai_usage_logs'; // see AIProvider for why this is needed

    protected $fillable = [
        'tenant_id', 'ai_provider_id', 'feature_key', 'model',
        'prompt_tokens', 'completion_tokens', 'estimated_cost',
        'status', 'error_detail',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }
}
