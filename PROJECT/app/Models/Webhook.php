<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    protected $fillable = [
        'tenant_id',
        'url',
        'event',
        'events',
        'headers',
        'is_active',
        'retry_count',
    ];

    protected $casts = [
        'events' => 'json',
        'headers' => 'json',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_triggered_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class)->latest();
    }

    /**
     * Get last 50 logs
     */
    public function recentLogs()
    {
        return $this->logs()->take(50)->get();
    }
}
