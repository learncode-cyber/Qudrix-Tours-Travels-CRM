<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class ValidateTenantScope
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user || !$request->user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $tenantId = $request->route('tenant_id');
        if ($tenantId && $tenantId != $request->user->tenant_id) {
            return response()->json(['error' => 'Tenant mismatch'], 403);
        }
        
        return $next($request);
    }
}
