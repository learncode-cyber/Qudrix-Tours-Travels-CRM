<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\AsJson;

class AuditLog extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'action', 'entity_type', 'entity_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'description'
    ];

    protected $casts = [
        'old_values' => AsJson::class,
        'new_values' => AsJson::class,
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getChangesSummary(): array
    {
        return [
            'action' => $this->action,
            'entity' => "{$this->entity_type}::{$this->entity_id}",
            'changes' => [
                'old' => $this->old_values,
                'new' => $this->new_values,
            ]
        ];
    }
}
