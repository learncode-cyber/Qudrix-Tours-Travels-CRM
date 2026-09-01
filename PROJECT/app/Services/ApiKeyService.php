<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\ApiLog;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * API Key Service
 * Handles API key generation, validation, rotation, revocation, and logging
 */
class ApiKeyService
{
    /**
     * Create a new API key
     */
    public function createKey(
        string $name,
        int $tenantId,
        array $permissions,
        ?string $description = null,
        ?Carbon $expiresAt = null,
        ?int $createdBy = null
    ): ApiKey {
        $key = 'ak_' . Str::random(32);
        $secret = 'sk_' . Str::random(64);
        
        // Hash the secret for storage
        $secretHash = hash('sha256', $secret);

        $apiKey = ApiKey::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'description' => $description,
            'key' => $key,
            'secret_hash' => $secretHash,
            'permissions' => json_encode($permissions),
            'status' => 'active',
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
            'usage_count' => 0,
            'last_used_at' => null,
        ]);

        // Return with plaintext secret (only shown once)
        $apiKey->secret = $secret;
        
        return $apiKey;
    }

    /**
     * Validate API key and secret
     */
    public function validateCredentials(string $key, string $secret): array
    {
        $apiKey = ApiKey::where('key', $key)->first();

        if (!$apiKey) {
            return ['valid' => false, 'key' => null];
        }

        if ($apiKey->status !== 'active') {
            return ['valid' => false, 'key' => $apiKey];
        }

        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            return ['valid' => false, 'key' => $apiKey];
        }

        $secretHash = hash('sha256', $secret);
        $isValid = hash_equals($apiKey->secret_hash, $secretHash);

        if (!$isValid) {
            return ['valid' => false, 'key' => $apiKey];
        }

        return ['valid' => true, 'key' => $apiKey];
    }

    /**
     * Check if API key has permission
     */
    public function hasPermission(ApiKey $apiKey, string $permission): bool
    {
        if ($apiKey->status !== 'active') {
            return false;
        }

        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            return false;
        }

        $permissions = is_string($apiKey->permissions) 
            ? json_decode($apiKey->permissions, true)
            : $apiKey->permissions;

        return in_array($permission, $permissions ?? []);
    }

    /**
     * Rotate API key (generate new secret, invalidate old one)
     */
    public function rotateKey(ApiKey $oldKey): ApiKey
    {
        $newKey = 'ak_' . Str::random(32);
        $newSecret = 'sk_' . Str::random(64);
        $newSecretHash = hash('sha256', $newSecret);

        // Create new API key with same permissions
        $rotatedKey = ApiKey::create([
            'tenant_id' => $oldKey->tenant_id,
            'name' => $oldKey->name,
            'description' => $oldKey->description,
            'key' => $newKey,
            'secret_hash' => $newSecretHash,
            'permissions' => $oldKey->permissions,
            'status' => 'active',
            'expires_at' => $oldKey->expires_at,
            'created_by' => auth()->id(),
            'rotated_from_id' => $oldKey->id,
            'usage_count' => 0,
        ]);

        // Revoke old key
        $oldKey->update([
            'status' => 'rotated',
            'revoked_at' => now(),
        ]);

        // Return new key with plaintext secret
        $rotatedKey->secret = $newSecret;
        
        return $rotatedKey;
    }

    /**
     * Revoke API key
     */
    public function revokeKey(ApiKey $apiKey): void
    {
        $apiKey->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        \Log::info('API key revoked', [
            'key_id' => $apiKey->id,
            'key_name' => $apiKey->name,
        ]);
    }

    /**
     * Log API request
     */
    public function logRequest(
        ApiKey $apiKey,
        string $method,
        string $endpoint,
        int $statusCode,
        float $responseTimeMs,
        array $metadata = []
    ): ApiLog {
        // Update API key's last used time and usage count
        $apiKey->increment('usage_count');
        $apiKey->update(['last_used_at' => now()]);

        return ApiLog::create([
            'api_key_id' => $apiKey->id,
            'tenant_id' => $apiKey->tenant_id,
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'response_time_ms' => $responseTimeMs,
            'metadata' => json_encode($metadata),
        ]);
    }

    /**
     * Get API key by key and validate it
     */
    public function getValidKey(string $key): ?ApiKey
    {
        $apiKey = ApiKey::where('key', $key)
            ->where('status', 'active')
            ->first();

        if (!$apiKey) {
            return null;
        }

        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            return null;
        }

        return $apiKey;
    }

    /**
     * Get usage statistics for API key
     */
    public function getUsageStats(
        ApiKey $apiKey,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $logs = ApiLog::where('api_key_id', $apiKey->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $successfulRequests = $logs->where('status_code', '>=', 200)
            ->where('status_code', '<', 300)
            ->count();

        $failedRequests = $logs->where('status_code', '>=', 400)->count();

        return [
            'total_requests' => $logs->count(),
            'successful_requests' => $successfulRequests,
            'failed_requests' => $failedRequests,
            'success_rate' => $logs->count() > 0 
                ? round(($successfulRequests / $logs->count()) * 100, 2)
                : 0,
            'endpoints_used' => $logs->pluck('endpoint')->unique()->count(),
            'average_response_time_ms' => round($logs->avg('response_time_ms') ?? 0, 2),
            'peak_hour' => $logs->groupBy(function ($log) {
                return $log->created_at->format('H:00');
            })->sortByDesc('count')->keys()->first() ?? null,
        ];
    }

    /**
     * Clean up expired API keys
     */
    public function cleanupExpiredKeys(): int
    {
        $cleaned = ApiKey::where('expires_at', '<', now())
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        if ($cleaned > 0) {
            \Log::info("Cleaned up {$cleaned} expired API keys");
        }

        return $cleaned;
    }

    /**
     * Get all permissions available in the system
     */
    public function getAvailablePermissions(): array
    {
        return [
            'packages:read' => 'Read all packages',
            'packages:create' => 'Create packages',
            'packages:update' => 'Update packages',
            'packages:delete' => 'Delete packages',
            'bookings:create' => 'Create bookings',
            'bookings:read' => 'Read bookings',
            'bookings:update' => 'Update bookings',
            'bookings:cancel' => 'Cancel bookings',
            'quotations:create' => 'Create quotations',
            'quotations:read' => 'Read quotations',
            'quotations:update' => 'Update quotations',
            'customers:create' => 'Create customers',
            'customers:read' => 'Read customers',
            'customers:update' => 'Update customers',
            'payments:read' => 'Read payments',
            'payments:create' => 'Create payments',
            'analytics:read' => 'Read analytics',
            'webhooks:manage' => 'Manage webhooks',
        ];
    }
}
