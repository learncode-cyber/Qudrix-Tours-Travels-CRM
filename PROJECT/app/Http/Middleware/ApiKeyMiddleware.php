<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\ApiKey;
use App\Services\ApiKeyService;

/**
 * API Key Middleware
 * Validates API key and secret from request headers
 * 
 * Header format:
 * Authorization: Bearer ak_xxxxx
 * X-API-Secret: sk_xxxxx
 */
class ApiKeyMiddleware
{
    protected $apiKeyService;

    public function __construct(ApiKeyService $apiKeyService)
    {
        $this->apiKeyService = $apiKeyService;
    }

    public function handle(Request $request, Closure $next)
    {
        // Extract API key and secret from headers
        $authHeader = $request->header('Authorization', '');
        $secret = $request->header('X-API-Secret', '');

        // Parse Bearer token
        if (strpos($authHeader, 'Bearer ') === 0) {
            $key = substr($authHeader, 7);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Missing or invalid API key',
                'code' => 'MISSING_API_KEY',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (empty($secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing API secret',
                'code' => 'MISSING_API_SECRET',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Validate credentials
        $validation = $this->apiKeyService->validateCredentials($key, $secret);

        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API credentials',
                'code' => 'INVALID_CREDENTIALS',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = $validation['key'];

        // FIX (Phase 3 audit): api_keys table has `is_active` (boolean),
        // not `status` (string) — that column doesn't exist, so this
        // always evaluated null !== 'active' (true), rejecting every
        // key regardless of its real state. This is almost certainly why
        // this middleware was never wired into routes/api-public.php's
        // route group despite the file's own doc-comment claiming API key
        // auth was enforced.
        if (!$apiKey->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'API key is inactive',
                'code' => 'KEY_NOT_ACTIVE',
            ], Response::HTTP_FORBIDDEN);
        }

        // Check expiration
        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'API key has expired',
                'code' => 'KEY_EXPIRED',
            ], Response::HTTP_FORBIDDEN);
        }

        // Check rate limit
        $requestsPerMinute = 60; // Default: 60 requests per minute
        if ($this->isRateLimited($apiKey, $requestsPerMinute)) {
            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded',
                'code' => 'RATE_LIMIT_EXCEEDED',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Store API key in request for later use
        $request->apiKey = $apiKey;

        // FIX (Phase 3 audit): nothing previously resolved a tenant context
        // from the API key, so every public controller had no reliable way
        // to know which tenant a website request belonged to. Mirrors how
        // TenantMiddleware exposes tenant_id for the authenticated flow.
        app()->instance('tenant_id', $apiKey->tenant_id);
        $request->tenant_id = $apiKey->tenant_id;

        // Record request start time
        $request->requestStartTime = microtime(true);

        // Call next middleware/controller
        $response = $next($request);

        // Log the request
        $this->logRequest($request, $response, $apiKey);

        // Add rate limit headers
        return $response
            ->header('X-RateLimit-Limit', $requestsPerMinute)
            ->header('X-RateLimit-Remaining', max(0, $requestsPerMinute - $apiKey->usage_count % $requestsPerMinute))
            ->header('X-RateLimit-Reset', (int)(time() + 60))
            ->header('X-API-Key-Name', $apiKey->name);
    }

    /**
     * Check if API key is rate limited
     */
    private function isRateLimited(ApiKey $apiKey, int $requestsPerMinute): bool
    {
        $cacheKey = "api_key_rate_limit:{$apiKey->id}";
        $count = \Cache::get($cacheKey, 0);

        if ($count >= $requestsPerMinute) {
            return true;
        }

        // Increment counter with 60-second TTL
        \Cache::put($cacheKey, $count + 1, 60);

        return false;
    }

    /**
     * Log API request
     */
    private function logRequest(Request $request, $response, ApiKey $apiKey): void
    {
        try {
            $responseTimeMs = round((microtime(true) - $request->requestStartTime) * 1000, 2);
            $statusCode = $response->status();

            // Log only for non-200 responses or specifically flagged requests
            if ($statusCode >= 400 || $request->header('X-Log-Request') === 'true') {
                app(ApiKeyService::class)->logRequest(
                    $apiKey,
                    $request->method(),
                    $request->path(),
                    $statusCode,
                    $responseTimeMs,
                    [
                        'ip' => $request->ip(),
                        'user_agent' => $request->header('User-Agent'),
                        'query_params' => $request->query(),
                    ]
                );
            }
        } catch (\Exception $e) {
            \Log::error('Failed to log API request', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
