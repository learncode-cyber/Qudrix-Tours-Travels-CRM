<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'user_id', 'method', 'url', 'route_name', 'ip_address',
        'user_agent', 'status_code', 'duration_ms', 'is_suspicious',
        'suspicion_reason', 'created_at',
    ];

    protected $casts = [
        'is_suspicious' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
