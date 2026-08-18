<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user ?? auth()->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'Tenant not identified'], 401);
        }

        // Set tenant context globally
        app()->instance('tenant_id', $user->tenant_id);

        // Add tenant_id to all queries
        \Illuminate\Database\Eloquent\Model::addGlobalScope('tenant', function($query) use ($user) {
            if ($query->getModel()->getTable() !== 'tenants') {
                $query->where('tenant_id', $user->tenant_id);
            }
        });

        return $next($request);
    }
}
