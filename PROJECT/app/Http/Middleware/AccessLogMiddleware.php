<?php

namespace App\Http\Middleware;

use App\Models\AccessLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// Records every request's IP, URL, method, user agent, status code and
// timestamp (Directive S19), and flags the ones worth a human look.
//
// This is distinct from AuditMiddleware: audit records what business data
// changed; this records who touched the system and how, including reads
// and rejected requests that never change anything.
class AccessLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $startedAt = microtime(true);

        $response = $next($request);

        // Logging must never break the request it is observing.
        try {
            $this->record($request, $response, (int) ((microtime(true) - $startedAt) * 1000));
        } catch (\Throwable $e) {
            Log::warning('Access log write failed', ['error' => $e->getMessage()]);
        }

        return $response;
    }

    private function record(Request $request, $response, int $durationMs): void
    {
        if (!config('security.access_log.enabled', true)) {
            return;
        }

        $path = $request->path();
        foreach ((array) config('security.access_log.ignore_paths', []) as $ignored) {
            if ($path === $ignored || str_starts_with($path, rtrim($ignored, '/') . '/')) {
                return;
            }
        }

        $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null;
        [$suspicious, $reason] = $this->assess($status, $durationMs);

        $user = $request->user ?? null;

        AccessLog::create([
            'tenant_id' => $user->tenant_id ?? null,
            'user_id' => $user->id ?? null,
            'method' => $request->method(),
            // Full URL including query string; note that request BODIES are
            // deliberately never stored here — they can contain passwords
            // and provider credentials.
            'url' => $this->safeUrl($request),
            'route_name' => optional($request->route())->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_strcut((string) $request->userAgent(), 0, 1000),
            'status_code' => $status,
            'duration_ms' => $durationMs,
            'is_suspicious' => $suspicious,
            'suspicion_reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * What counts as worth reviewing.
     */
    private function assess(?int $status, int $durationMs): array
    {
        if ($status === 401) {
            return [true, 'Unauthenticated request'];
        }
        if ($status === 403) {
            return [true, 'Forbidden — authenticated but not permitted'];
        }
        if ($status === 429) {
            return [true, 'Rate limit exceeded'];
        }
        if ($status !== null && $status >= 500) {
            return [true, "Server error ({$status})"];
        }

        $slowMs = (int) config('security.access_log.slow_request_ms', 3000);
        if ($slowMs > 0 && $durationMs >= $slowMs) {
            return [true, "Slow request ({$durationMs}ms)"];
        }

        return [false, null];
    }

    /**
     * Strips sensitive values out of the query string before it is stored.
     * A token in a URL would otherwise be written to the log table in clear.
     */
    private function safeUrl(Request $request): string
    {
        $redactKeys = array_map('strtolower', (array) config('security.access_log.redact_keys', []));
        $query = $request->query();

        foreach ($query as $key => $value) {
            if (in_array(strtolower((string) $key), $redactKeys, true)) {
                $query[$key] = '[REDACTED]';
            }
        }

        $base = $request->url();

        return empty($query) ? $base : $base . '?' . http_build_query($query);
    }
}
