<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

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
        'sync_settings' => 'json',
        'custom_mappings' => 'json',
        'is_active' => 'boolean',
        'last_connection_test_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Encrypt API key before saving
     */
    public function setCredentialsAttribute($value): void
    {
        if (is_array($value)) {
            if (isset($value['crm_api_key'])) {
                $this->attributes['crm_api_key'] = Crypt::encryptString($value['crm_api_key']);
            }
            if (isset($value['crm_api_secret'])) {
                $this->attributes['crm_api_secret'] = Crypt::encryptString($value['crm_api_secret']);
            }
        }
    }

    /**
     * Decrypt API key when accessing
     */
    public function getDecryptedApiKey(): string
    {
        if (!$this->crm_api_key) {
            return '';
        }
        
        try {
            return Crypt::decryptString($this->crm_api_key);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt API key', ['integration_id' => $this->id]);
            return '';
        }
    }

    /**
     * Decrypt API secret when accessing
     */
    public function getDecryptedApiSecret(): string
    {
        if (!$this->crm_api_secret) {
            return '';
        }
        
        try {
            return Crypt::decryptString($this->crm_api_secret);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt API secret', ['integration_id' => $this->id]);
            return '';
        }
    }

    /**
     * Get webhook secret (decrypted)
     */
    public function getDecryptedWebhookSecret(): string
    {
        if (!$this->webhook_secret) {
            return '';
        }
        
        try {
            return Crypt::decryptString($this->webhook_secret);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt webhook secret', ['integration_id' => $this->id]);
            return '';
        }
    }

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
     * Get integration config for API client
     */
    public function getApiConfig(): array
    {
        return [
            'base_url' => $this->crm_base_url,
            'api_key' => $this->getDecryptedApiKey(),
            'api_secret' => $this->getDecryptedApiSecret(),
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
