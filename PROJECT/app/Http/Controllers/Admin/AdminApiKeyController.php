<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Services\ApiKeyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * Admin API Key Management Controller
 * For CRM Admin Panel: Settings → Integrations → API Keys
 */
class AdminApiKeyController extends Controller
{
    protected $apiKeyService;

    public function __construct(ApiKeyService $apiKeyService)
    {
        $this->apiKeyService = $apiKeyService;
        $this->middleware('auth:api');
        $this->middleware('role:super-admin,admin');
    }

    /**
     * Get all API keys for this tenant/agency
     * 
     * GET /admin/api/api-keys
     */
    public function index(Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;
            $page = max(1, (int) $request->get('page', 1));
            $limit = min(50, max(1, (int) $request->get('limit', 10)));

            $keys = ApiKey::where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $keys->items(),
                'pagination' => [
                    'current_page' => $keys->currentPage(),
                    'per_page' => $keys->perPage(),
                    'total' => $keys->total(),
                    'total_pages' => $keys->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch API keys',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new API key
     * 
     * POST /admin/api/api-keys
     * 
     * Body:
     * {
     *   "name": "Website Integration",
     *   "permissions": ["packages:read", "bookings:create", "quotations:create"],
     *   "description": "For QUDRIX website integration",
     *   "expires_in_days": 365
     * }
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|min:3|max:255|unique:api_keys,name',
                'permissions' => 'required|array|min:1',
                'permissions.*' => 'string|in:packages:read,bookings:create,bookings:read,quotations:create,quotations:read,customers:create,customers:read,payments:read,analytics:read',
                'description' => 'nullable|string|max:500',
                'expires_in_days' => 'nullable|integer|min:1|max:3650',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;
            $expiresAt = $request->expires_in_days 
                ? now()->addDays($request->expires_in_days)
                : now()->addYear();

            // Create API key using service
            $apiKey = $this->apiKeyService->createKey(
                name: $request->name,
                tenantId: $tenantId,
                permissions: $request->permissions,
                description: $request->description,
                expiresAt: $expiresAt,
                createdBy: auth()->id()
            );

            // Return key details (secret shown only once)
            return response()->json([
                'success' => true,
                'message' => 'API key created successfully',
                'data' => [
                    'id' => $apiKey->id,
                    'key' => $apiKey->key,
                    'secret' => $apiKey->secret, // IMPORTANT: Shown only once
                    'name' => $apiKey->name,
                    'permissions' => $apiKey->permissions,
                    'status' => $apiKey->status,
                    'expires_at' => $apiKey->expires_at?->toIso8601String(),
                    'created_at' => $apiKey->created_at->toIso8601String(),
                ],
                'warning' => 'IMPORTANT: Store the secret key in a secure location. It will not be shown again.',
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            \Log::error('API key creation failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create API key',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get single API key details (secret NOT shown)
     * 
     * GET /admin/api/api-keys/{id}
     */
    public function show($id)
    {
        try {
            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;
            
            $apiKey = ApiKey::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'API key not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $apiKey->id,
                    'key' => $apiKey->key,
                    'name' => $apiKey->name,
                    'description' => $apiKey->description,
                    'permissions' => $apiKey->permissions,
                    'status' => $apiKey->status,
                    'last_used_at' => $apiKey->last_used_at?->toIso8601String(),
                    'usage_count' => $apiKey->usage_count ?? 0,
                    'expires_at' => $apiKey->expires_at?->toIso8601String(),
                    'created_at' => $apiKey->created_at->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch API key',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Rotate API key (generate new secret, invalidate old one)
     * 
     * POST /admin/api/api-keys/{id}/rotate
     */
    public function rotate($id)
    {
        try {
            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;
            
            $apiKey = ApiKey::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'API key not found',
                ], Response::HTTP_NOT_FOUND);
            }

            if ($apiKey->status === 'revoked') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot rotate a revoked key',
                ], Response::HTTP_BAD_REQUEST);
            }

            $newApiKey = $this->apiKeyService->rotateKey($apiKey);

            return response()->json([
                'success' => true,
                'message' => 'API key rotated successfully',
                'data' => [
                    'id' => $newApiKey->id,
                    'key' => $newApiKey->key,
                    'secret' => $newApiKey->secret, // NEW secret
                    'old_key_invalidated_at' => now()->toIso8601String(),
                ],
                'warning' => 'IMPORTANT: Old key is no longer valid. Update your applications immediately.',
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            \Log::error('API key rotation failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'key_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to rotate API key',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Revoke API key
     * 
     * POST /admin/api/api-keys/{id}/revoke
     */
    public function revoke($id)
    {
        try {
            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;
            
            $apiKey = ApiKey::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'API key not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $this->apiKeyService->revokeKey($apiKey);

            \Log::warning('API key revoked', [
                'key_id' => $apiKey->id,
                'revoked_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'API key revoked successfully',
                'data' => [
                    'id' => $apiKey->id,
                    'status' => 'revoked',
                    'revoked_at' => now()->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke API key',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get API usage statistics
     * 
     * GET /admin/api/api-keys/{id}/usage
     * Query: ?period=7days (7days, 30days, 90days, all)
     */
    public function usage($id, Request $request)
    {
        try {
            $tenantId = auth()->user()->tenant_id ?? auth()->user()->organization_id;
            $period = $request->get('period', '7days');
            
            $apiKey = ApiKey::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'API key not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Calculate date range
            $startDate = match($period) {
                '7days' => now()->subDays(7),
                '30days' => now()->subDays(30),
                '90days' => now()->subDays(90),
                'all' => $apiKey->created_at,
                default => now()->subDays(7),
            };

            $logs = ApiLog::where('api_key_id', $apiKey->id)
                ->where('created_at', '>=', $startDate)
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate statistics
            $stats = [
                'total_requests' => $logs->count(),
                'successful_requests' => $logs->where('status_code', '>=', 200)->where('status_code', '<', 300)->count(),
                'failed_requests' => $logs->where('status_code', '>=', 400)->count(),
                'endpoints_used' => $logs->pluck('endpoint')->unique()->count(),
                'average_response_time' => round($logs->avg('response_time_ms'), 2),
                'last_request_at' => $logs->max('created_at')?->toIso8601String(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'api_key_id' => $apiKey->id,
                    'api_key_name' => $apiKey->name,
                    'period' => $period,
                    'start_date' => $startDate->toIso8601String(),
                    'end_date' => now()->toIso8601String(),
                    'stats' => $stats,
                    'recent_requests' => $logs->take(10)->map(fn($log) => [
                        'endpoint' => $log->endpoint,
                        'method' => $log->method,
                        'status_code' => $log->status_code,
                        'response_time_ms' => $log->response_time_ms,
                        'created_at' => $log->created_at->toIso8601String(),
                    ]),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch usage statistics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get API connection test
     * 
     * POST /admin/api/test-connection
     * 
     * Body: { "key": "...", "secret": "..." }
     */
    public function testConnection(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'key' => 'required|string',
                'secret' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $result = $this->apiKeyService->validateCredentials(
                $request->key,
                $request->secret
            );

            if (!$result['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return response()->json([
                'success' => true,
                'message' => 'Connection test successful',
                'data' => [
                    'connected' => true,
                    'api_key_name' => $result['key']->name,
                    'permissions' => $result['key']->permissions,
                    'status' => $result['key']->status,
                    'expires_at' => $result['key']->expires_at?->toIso8601String(),
                    'crm_version' => '1.0.0',
                    'tenant_id' => $result['key']->tenant_id,
                    'latency_ms' => round((microtime(true) - request()->server('REQUEST_TIME_FLOAT', microtime(true))) * 1000, 2),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
