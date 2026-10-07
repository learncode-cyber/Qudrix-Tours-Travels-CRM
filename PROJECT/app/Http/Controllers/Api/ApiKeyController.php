<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApiKeyController extends Controller
{
    protected ApiKeyService $service;

    public function __construct(ApiKeyService $service)
    {
        $this->service = $service;
        $this->middleware('auth:api');
    }

    /**
     * Get all API keys
     * GET /api/v1/api-keys
     */
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $keys = $this->service->getAll($tenantId);

        return response()->json([
            'success' => true,
            'data' => $keys->map(fn($key) => [
                'id' => $key->id,
                'name' => $key->name,
                'key' => $key->key,
                'description' => $key->description,
                'rate_limit' => $key->rate_limit,
                'is_active' => $key->is_active,
                'used_count' => $key->used_count,
                'last_used_at' => $key->last_used_at,
                'expires_at' => $key->expires_at,
                'created_at' => $key->created_at,
            ])->toArray(),
        ]);
    }

    /**
     * Create new API key
     * POST /api/v1/api-keys
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'rate_limit' => 'nullable|integer|min:1|max:10000',
            'permissions' => 'nullable|array',
            'allowed_ips' => 'nullable|array',
            'expires_at' => 'nullable|date',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $key = $this->service->create($tenantId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'API key created successfully',
            'data' => [
                'id' => $key->id,
                'name' => $key->name,
                'key' => $key->key,
                'secret' => $key->secret,
                'rate_limit' => $key->rate_limit,
                'expires_at' => $key->expires_at,
                'created_at' => $key->created_at,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Get single API key
     * GET /api/v1/api-keys/{id}
     */
    public function show(ApiKey $apiKey)
    {
        $this->authorize('view', $apiKey);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key' => $apiKey->key,
                'description' => $apiKey->description,
                'permissions' => $apiKey->permissions,
                'allowed_ips' => $apiKey->allowed_ips,
                'rate_limit' => $apiKey->rate_limit,
                'is_active' => $apiKey->is_active,
                'used_count' => $apiKey->used_count,
                'last_used_at' => $apiKey->last_used_at,
                'expires_at' => $apiKey->expires_at,
                'created_at' => $apiKey->created_at,
            ],
        ]);
    }

    /**
     * Update API key
     * PATCH /api/v1/api-keys/{id}
     */
    public function update(Request $request, ApiKey $apiKey)
    {
        $this->authorize('update', $apiKey);

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'rate_limit' => 'nullable|integer|min:1|max:10000',
            'permissions' => 'nullable|array',
            'allowed_ips' => 'nullable|array',
            'expires_at' => 'nullable|date',
        ]);

        $key = $this->service->update($apiKey->id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'API key updated successfully',
            'data' => $key,
        ]);
    }

    /**
     * Revoke API key
     * POST /api/v1/api-keys/{id}/revoke
     */
    public function revoke(ApiKey $apiKey)
    {
        $this->authorize('delete', $apiKey);

        $this->service->revoke($apiKey->id);

        return response()->json([
            'success' => true,
            'message' => 'API key revoked successfully',
        ]);
    }

    /**
     * Delete API key
     * DELETE /api/v1/api-keys/{id}
     */
    public function destroy(ApiKey $apiKey)
    {
        $this->authorize('delete', $apiKey);

        $this->service->delete($apiKey->id);

        return response()->json([
            'success' => true,
            'message' => 'API key deleted successfully',
        ]);
    }

    /**
     * Get API logs
     * GET /api/v1/api-keys/logs
     */
    public function logs(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $logs = $this->service->getLogs($tenantId, $request->get('limit', 100));

        return response()->json([
            'success' => true,
            'data' => $logs->map(fn($log) => [
                'id' => $log->id,
                'api_key' => $log->apiKey?->name,
                'method' => $log->method,
                'endpoint' => $log->endpoint,
                'status_code' => $log->status_code,
                'response_time' => $log->response_time,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at,
            ])->toArray(),
        ]);
    }

    /**
     * Get API statistics
     * GET /api/v1/api-keys/stats
     */
    public function stats(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $period = $request->get('period', '7 days');
        $stats = $this->service->getStats($tenantId, $period);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
