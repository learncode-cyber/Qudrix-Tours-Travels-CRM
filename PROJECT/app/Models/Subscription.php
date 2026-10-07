<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id', 'plan_id', 'status', 'billing_cycle',
        'current_period_start', 'current_period_end', 'renewal_date',
        'cancel_at', 'canceled_at', 'cancellation_reason',
        'payment_method_id', 'next_billing_amount', 'notes',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'renewal_date' => 'datetime',
        'cancel_at' => 'datetime',
        'canceled_at' => 'datetime',
        'next_billing_amount' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(SubscriptionUsageLog::class);
    }
}
