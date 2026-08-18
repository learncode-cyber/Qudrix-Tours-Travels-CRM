<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsiteIntegration;
use App\Services\IntegrationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * Integration Controller
 * For Website Admin Panel: Settings → CRM Integration
 * Handles website ↔ CRM integration configuration
 */
class IntegrationController extends Controller
{
    public function __construct(
        private IntegrationService $integrationService
    ) {
        $this->middleware('auth:api');
        $this->middleware('role:super-admin,admin');
    }

    /**
     * List all integrations for this tenant
     * 
     * GET /admin/api/integrations
     */
    public function index(Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;
            $activeOnly = (bool) $request->query('active_only', false);

            $integrations = $this->integrationService->getIntegrations($tenantId, $activeOnly);

            return response()->json([
                'success' => true,
                'data' => $integrations->map(fn($i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'website_url' => $i->website_url,
                    'status' => $i->status,
                    'is_active' => $i->is_active,
                    'integration_type' => $i->integration_type,
                    'last_connection_test_at' => $i->last_connection_test_at?->toIso8601String(),
                    'last_connection_status' => $i->last_connection_status,
                    'last_sync_at' => $i->last_sync_at?->toIso8601String(),
                    'created_at' => $i->created_at->toIso8601String(),
                ]),
                'count' => $integrations->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch integrations',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create new integration
     * 
     * POST /admin/api/integrations
     * 
     * Body:
     * {
     *   "name": "My Website",
     *   "website_url": "https://mywebsite.com",
     *   "crm_base_url": "https://crm.yourdomain.com/api/v1",
     *   "description": "Main booking website",
     *   "sync_settings": {
     *     "auto_sync": true,
     *     "sync_interval_minutes": 15,
     *     "entities": ["leads", "bookings", "customers"]
     *   }
     * }
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|min:2|max:255',
                'website_url' => 'required|url|max:500',
                'crm_base_url' => 'required|url|max:500',
                'description' => 'nullable|string|max:1000',
                'sync_settings' => 'nullable|array',
                'integration_type' => 'nullable|in:website,external_system,mobile_app',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;

            $integration = $this->integrationService->createIntegration(
                tenantId: $tenantId,
                name: $request->name,
                websiteUrl: $request->website_url,
                crmBaseUrl: $request->crm_base_url,
                description: $request->description,
                syncSettings: $request->sync_settings ?? [],
                integrationType: $request->integration_type ?? 'website'
            );

            return response()->json([
                'success' => true,
                'message' => 'Integration created successfully',
                'data' => [
                    'id' => $integration->id,
                    'name' => $integration->name,
                    'status' => $integration->status,
                    'webhook_secret' => $this->integrationService->generateWebhookSecret($integration),
                    'webhook_url' => $this->integrationService->getWebhookUrl($integration),
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            \Log::error('Integration creation failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create integration',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get integration details
     * 
     * GET /admin/api/integrations/{id}
     */
    public function show($id)
    {
        try {
            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;
            $integration = $this->integrationService->getIntegrationForTenant($id, $tenantId);

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Integration not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $stats = $this->integrationService->getStatistics($integration);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $integration->id,
                    'name' => $integration->name,
                    'website_url' => $integration->website_url,
                    'crm_base_url' => $integration->crm_base_url,
                    'description' => $integration->description,
                    'status' => $integration->status,
                    'is_active' => $integration->is_active,
                    'integration_type' => $integration->integration_type,
                    'webhook_url' => $this->integrationService->getWebhookUrl($integration),
                    'last_connection_test_at' => $integration->last_connection_test_at?->toIso8601String(),
                    'last_connection_status' => $integration->last_connection_status,
                    'last_sync_at' => $integration->last_sync_at?->toIso8601String(),
                    'sync_settings' => $integration->sync_settings,
                    'statistics' => $stats,
                    'created_at' => $integration->created_at->toIso8601String(),
                    'updated_at' => $integration->updated_at->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch integration',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update integration
     * 
     * PUT /admin/api/integrations/{id}
     */
    public function update($id, Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;
            $integration = $this->integrationService->getIntegrationForTenant($id, $tenantId);

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Integration not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|min:2|max:255',
                'description' => 'sometimes|nullable|string|max:1000',
                'crm_base_url' => 'sometimes|url|max:500',
                'sync_settings' => 'sometimes|nullable|array',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $integration->update($validator->validated());

            $this->integrationService->logAudit(
                $integration->id,
                'update',
                null,
                $validator->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Integration updated successfully',
                'data' => [
                    'id' => $integration->id,
                    'status' => $integration->status,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update integration',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Save CRM credentials
     * 
     * POST /admin/api/integrations/{id}/credentials
     * 
     * Body:
     * {
     *   "api_key": "qd_xxxxx",
     *   "api_secret": "sk_xxxxx"
     * }
     */
    public function saveCredentials($id, Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;
            $integration = $this->integrationService->getIntegrationForTenant($id, $tenantId);

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Integration not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'api_key' => 'required|string|min:10',
                'api_secret' => 'required|string|min:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->integrationService->updateCredentials(
                $integration,
                $request->api_key,
                $request->api_secret,
                'Credentials updated from admin panel'
            );

            return response()->json([
                'success' => true,
                'message' => 'Credentials saved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save credentials',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Test CRM connection
     * 
     * POST /admin/api/integrations/{id}/test-connection
     */
    public function testConnection($id)
    {
        try {
            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;
            $integration = $this->integrationService->getIntegrationForTenant($id, $tenantId);

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Integration not found',
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$integration->crm_api_key || !$integration->crm_api_secret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credentials not configured',
                ], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->integrationService->testConnection($integration);

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get audit logs
     * 
     * GET /admin/api/integrations/{id}/audit-logs
     */
    public function auditLogs($id, Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;
            $integration = $this->integrationService->getIntegrationForTenant($id, $tenantId);

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Integration not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $limit = min(100, max(1, (int) $request->query('limit', 50)));
            $logs = $integration->auditLogs()
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $logs->map(fn($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user?->name,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'reason' => $log->reason,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->toIso8601String(),
                ]),
                'count' => $logs->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch audit logs',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete integration
     * 
     * DELETE /admin/api/integrations/{id}
     */
    public function destroy($id, Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;
            $integration = $this->integrationService->getIntegrationForTenant($id, $tenantId);

            if (!$integration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Integration not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $this->integrationService->deleteIntegration(
                $integration,
                $request->input('reason', 'Deleted from admin panel')
            );

            return response()->json([
                'success' => true,
                'message' => 'Integration deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete integration',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
