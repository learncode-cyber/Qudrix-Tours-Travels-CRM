<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditMiddleware
{
    private $auditableMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
    // FIX (Phase 15 security audit): Request::is() matches against the
    // path WITHOUT a leading slash — these patterns had one, so
    // shouldSkip() could never actually match anything, meaning
    // health-check and login requests were being audit-logged as if
    // they were regular authenticated actions (harmless but noisy/
    // incorrect, and the intent was clearly to skip them).
    private $skipPaths = ['api/v1/health', 'api/v1/login'];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $shouldAudit = in_array($request->method(), $this->auditableMethods)
            && !$this->shouldSkip($request);

        if ($shouldAudit && $request->user) {
            AuditLog::create([
                'tenant_id' => $request->user->tenant_id,
                'user_id' => $request->user->id,
                'action' => $request->method(),
                'entity_type' => $this->getEntityType($request),
                'entity_id' => $this->getEntityId($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'description' => "{$request->method()} {$request->path()}",
                'created_at' => now(),
            ]);
        }

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        foreach ($this->skipPaths as $path) {
            if ($request->is($path)) {
                return true;
            }
        }
        return false;
    }

    private function getEntityType(Request $request): string
    {
        // FIX (Phase 15 security audit): Laravel's routes/api.php is
        // auto-prefixed with /api (bootstrap/app.php's withRouting), so
        // a real request path is "api/leads/5" — segments[0]='api',
        // [1]='leads', [2]='5'. This previously read segments[2]/[3]
        // (off by one), meaning entity_type was actually recording the
        // numeric ID and entity_id was always null — the audit trail
        // was logging the wrong data for every single request.
        $segments = explode('/', trim($request->path(), '/'));
        return $segments[1] ?? 'unknown';
    }

    private function getEntityId(Request $request): ?int
    {
        $segments = explode('/', trim($request->path(), '/'));
        return isset($segments[2]) && is_numeric($segments[2]) ? (int) $segments[2] : null;
    }
}
