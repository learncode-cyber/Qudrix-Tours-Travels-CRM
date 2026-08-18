<?php

namespace App\Services;

use App\Models\WebsiteIntegration;
use App\Models\IntegrationSyncLog;
use App\Models\IntegrationAuditLog;
use App\Models\ApiKey;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class IntegrationService
{
    /**
     * Create or update website integration
     */
    public function createIntegration(
        int $tenantId,
        string $name,
        string $websiteUrl,
        string $crmBaseUrl,
        string $description = null,
        array $syncSettings = [],
        string $integrationType = 'website'
    ): WebsiteIntegration {
        $integration = WebsiteIntegration::updateOrCreate(
            ['tenant_id' => $tenantId, 'website_url' => $websiteUrl],
            [
                'name' => $name,
                'crm_base_url' => $crmBaseUrl,
                'description' => $description,
                'sync_settings' => $syncSettings,
                'integration_type' => $integrationType,
                'status' => 'pending',
                'is_active' => true,
                'webhook_secret' => Crypt::encryptString(Str::random(32)),
            ]
        );

        $this->logAudit($integration->id, 'create', null, [
            'name' => $name,
            'website_url' => $websiteUrl,
            'crm_base_url' => $crmBaseUrl,
        ]);

        return $integration;
    }

    /**
     * Save encrypted API credentials
     */
    public function updateCredentials(
        WebsiteIntegration $integration,
        string $apiKey,
        string $apiSecret,
        ?string $reason = null
    ): void {
        $oldValues = [
            'has_credentials' => !empty($integration->crm_api_key),
        ];

        $integration->update([
            'crm_api_key' => Crypt::encryptString($apiKey),
            'crm_api_secret' => Crypt::encryptString($apiSecret),
        ]);

        $this->logAudit($integration->id, 'credentials_change', $oldValues, [
            'credentials_updated' => true,
        ], $reason);
    }

    /**
     * Test connection to CRM
     */
    public function testConnection(WebsiteIntegration $integration): array
    {
        try {
            $config = $integration->getApiConfig();
            $startTime = microtime(true);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $config['api_key'],
                'X-API-Secret' => $config['api_secret'],
                'Content-Type' => 'application/json',
            ])->timeout(10)->get($config['base_url'] . '/health');

            $duration = round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $integration->update([
                    'status' => 'connected',
                    'last_connection_status' => 'success',
                    'last_connection_test_at' => now(),
                    'last_sync_error' => null,
                ]);

                $this->logAudit($integration->id, 'connection_test', null, [
                    'status' => 'success',
                    'response_code' => $response->status(),
                ]);

                return [
                    'success' => true,
                    'message' => 'Connection test successful',
                    'status_code' => $response->status(),
                    'latency_ms' => $duration,
                    'api_version' => $response->json('api_version'),
                ];
            }

            throw new \Exception('API returned status ' . $response->status());

        } catch (\Exception $e) {
            $integration->update([
                'status' => 'error',
                'last_connection_status' => 'failed',
                'last_connection_test_at' => now(),
                'last_sync_error' => $e->getMessage(),
            ]);

            $this->logAudit($integration->id, 'connection_test', null, [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate webhook secret for CRM
     */
    public function generateWebhookSecret(WebsiteIntegration $integration): string
    {
        $secret = Str::random(32);
        $integration->update([
            'webhook_secret' => Crypt::encryptString($secret),
        ]);

        return $secret;
    }

    /**
     * Get webhook URL for CRM to send data
     */
    public function getWebhookUrl(WebsiteIntegration $integration): string
    {
        return route('api.webhook.integration', [
            'integration_id' => $integration->id,
            'signature' => hash_hmac('sha256', $integration->id, $integration->getDecryptedWebhookSecret()),
        ]);
    }

    /**
     * Log integration activity
     */
    public function logAudit(
        int $integrationId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null
    ): void {
        IntegrationAuditLog::create([
            'website_integration_id' => $integrationId,
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Create sync log entry
     */
    public function createSyncLog(
        WebsiteIntegration $integration,
        string $syncType,
        string $entityType,
        int $entityCount = 0
    ): IntegrationSyncLog {
        return IntegrationSyncLog::create([
            'website_integration_id' => $integration->id,
            'sync_type' => $syncType,
            'entity_type' => $entityType,
            'entity_count' => $entityCount,
            'status' => 'pending',
            'started_at' => now(),
        ]);
    }

    /**
     * Get integration statistics
     */
    public function getStatistics(WebsiteIntegration $integration): array
    {
        $last30Days = now()->subDays(30);

        $syncLogs = $integration->syncLogs()
            ->where('created_at', '>=', $last30Days)
            ->get();

        $totalSyncs = $syncLogs->count();
        $successfulSyncs = $syncLogs->where('status', 'success')->count();
        $failedSyncs = $syncLogs->where('status', 'failed')->count();
        $totalEntities = $syncLogs->sum('entity_count');
        $avgDuration = $syncLogs->avg('duration_ms') ?? 0;

        return [
            'total_syncs_30d' => $totalSyncs,
            'successful_syncs' => $successfulSyncs,
            'failed_syncs' => $failedSyncs,
            'success_rate' => $totalSyncs > 0 ? round(($successfulSyncs / $totalSyncs) * 100, 2) : 0,
            'total_entities_synced' => $totalEntities,
            'average_sync_time_ms' => round($avgDuration, 2),
            'last_successful_sync' => $integration->syncLogs()
                ->where('status', 'success')
                ->latest('completed_at')
                ->first()?->completed_at?->toIso8601String(),
            'last_error' => $integration->last_sync_error,
        ];
    }

    /**
     * Delete integration (with audit)
     */
    public function deleteIntegration(WebsiteIntegration $integration, ?string $reason = null): void
    {
        $this->logAudit($integration->id, 'delete', [
            'name' => $integration->name,
            'website_url' => $integration->website_url,
        ], null, $reason);

        $integration->delete();
    }

    /**
     * Get all integrations for tenant
     */
    public function getIntegrations(int $tenantId, bool $activeOnly = false)
    {
        $query = WebsiteIntegration::where('tenant_id', $tenantId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get integration by ID with permission check
     */
    public function getIntegrationForTenant(int $integrationId, int $tenantId): ?WebsiteIntegration
    {
        return WebsiteIntegration::where('id', $integrationId)
            ->where('tenant_id', $tenantId)
            ->first();
    }
}
