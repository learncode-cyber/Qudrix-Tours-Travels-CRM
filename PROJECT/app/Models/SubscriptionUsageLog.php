<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionUsageLog extends Model
{
    protected $table = 'subscription_usage_logs';

    protected $fillable = [
        'tenant_id', 'subscription_id', 'metric_key',
        'value', 'reset_date', 'logged_at',
    ];

    protected $casts = [
        'reset_date' => 'datetime',
        'logged_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
