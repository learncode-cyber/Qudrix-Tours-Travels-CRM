<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'key',
        'secret',
        'description',
        'permissions',
        'allowed_ips',
        'rate_limit',
        'is_active',
        'expires_at',
    ];

    protected $hidden = ['secret'];

    protected $casts = [
        'permissions' => 'json',
        'allowed_ips' => 'json',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Generate a new API key
     */
    public static function generateKey(): string
    {
        do {
            $key = 'qd_' . bin2hex(random_bytes(24));
        } while (self::where('key', $key)->exists());
        
        return $key;
    }

    /**
     * Generate a new secret
     */
    public static function generateSecret(): string
    {
        return hash('sha256', bin2hex(random_bytes(32)));
    }

    /**
     * Check if key is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return now()->isAfter($this->expires_at);
    }

    /**
     * Check if key is valid
     */
    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * Check if IP is allowed
     */
    public function isIpAllowed(string $ip): bool
    {
        if (!$this->allowed_ips || empty($this->allowed_ips)) {
            return true;
        }

        return in_array($ip, $this->allowed_ips);
    }

    /**
     * Check permission
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->permissions) {
            return true;
        }

        return in_array($permission, $this->permissions);
    }

    /**
     * Record API usage
     */
    public function recordUsage(): void
    {
        $this->update([
            'last_used_at' => now(),
            'usage_count' => ($this->usage_count ?? 0) + 1,
        ]);
    }

    /**
     * Relationships
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }
}
