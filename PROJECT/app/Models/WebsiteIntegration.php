<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PHASE 16 SECURITY AUDIT FINDING: this model had a setCredentialsAttribute()
 * mutator that only fires when mass-assigning a 'credentials' key — but
 * 'credentials' was never in $fillable, and crm_api_key/crm_api_secret/
 * webhook_secret WERE directly fillable. That means the encryption
 * mutator could never actually trigger through normal use
 * (WebsiteIntegration::create(['crm_api_key' => $key, ...])) — these
 * three fields would have been saved as plaintext. Replaced with
 * Laravel's native 'encrypted' cast (same real APP_KEY-based approach
 * used for AIProvider in Phase 9), which encrypts/decrypts
 * transparently on every read/write — no separate getDecrypted*()
 * methods needed, and no way to accidentally bypass it via mass
 * assignment.
 */
class WebsiteIntegration extends Model
{
    use SoftDeletes;

    protected $table = 'website_integrations';

    protected $fillable = [
        'tenant_id',
        'name',
        'website_url',
        'description',
        'crm_api_key',
        'crm_api_secret',
        'crm_base_url',
        'webhook_secret',
        'webhook_url',
        'sync_settings',
        'status',
        'is_active',
        'last_connection_test_at',
        'last_connection_status',
        'last_sync_at',
        'last_sync_error',
        'integration_type',
        'custom_mappings',
    ];

    protected $hidden = [
        'crm_api_key',
        'crm_api_secret',
        'webhook_secret',
    ];

    protected $casts = [
        'crm_api_key' => 'encrypted',
        'crm_api_secret' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'sync_settings' => 'json',
        'custom_mappings' => 'json',
        'is_active' => 'boolean',
        'last_connection_test_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Check if integration is healthy
     */
    public function isHealthy(): bool
    {
        return $this->is_active 
            && $this->status === 'connected'
            && $this->last_connection_status === 'success';
    }

    /**
     * Check if sync is due
     */
    public function isSyncDue(int $intervalMinutes = 15): bool
    {
        if (!$this->last_sync_at) {
            return true;
        }

        return now()->diffInMinutes($this->last_sync_at) >= $intervalMinutes;
    }

    /**
     * Get integration config for API client. Attribute access below
     * transparently decrypts via the 'encrypted' cast — no manual
     * Crypt::decryptString() needed.
     */
    public function getApiConfig(): array
    {
        return [
            'base_url' => $this->crm_base_url,
            'api_key' => $this->crm_api_key ?? '',
            'api_secret' => $this->crm_api_secret ?? '',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];
    }

    /**
     * Relationships
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function syncLogs()
    {
        return $this->hasMany(IntegrationSyncLog::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(IntegrationAuditLog::class);
    }
}
