<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'ai_provider_id', 'user_id', 'feature', 'prompt_tokens',
        'completion_tokens', 'cost_usd', 'latency_ms', 'status', 'error_message',
        'created_at',
    ];

    protected $casts = [
        'cost_usd' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function aiProvider(): BelongsTo { return $this->belongsTo(AiProvider::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
