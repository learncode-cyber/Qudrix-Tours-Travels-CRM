<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditMiddleware
{
    private $auditableMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private $skipPaths = ['/api/v1/health', '/login'];

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
        $segments = explode('/', trim($request->path(), '/'));
        return $segments[2] ?? 'unknown';
    }

    private function getEntityId(Request $request): ?int
    {
        $segments = explode('/', trim($request->path(), '/'));
        return isset($segments[3]) ? (int)$segments[3] : null;
    }
}
