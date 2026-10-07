<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TenantMiddleware
{
    /**
     * Per-request cache of which tables actually have a tenant_id column,
     * so we don't run a schema lookup on every single query.
     *
     * FIX (Phase 1 audit): the original implementation applied a global
     * `tenant_id` scope to every Eloquent model unconditionally except
     * `tenants` itself. Several tables in this schema (e.g. webhooks,
     * api_settings, website_integration tables) do not have a tenant_id
     * column, so any query against them would have thrown an SQL error
     * ("Unknown column 'tenant_id'") the moment this middleware ran.
     */
    protected static array $tenantColumnCache = [];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user ?? auth()->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'Tenant not identified'], 401);
        }

        // Set tenant context globally
        app()->instance('tenant_id', $user->tenant_id);

        \Illuminate\Database\Eloquent\Model::addGlobalScope('tenant', function ($query) use ($user) {
            $table = $query->getModel()->getTable();

            if ($table === 'tenants') {
                return;
            }

            if (!array_key_exists($table, self::$tenantColumnCache)) {
                self::$tenantColumnCache[$table] = Schema::hasColumn($table, 'tenant_id');
            }

            if (self::$tenantColumnCache[$table]) {
                $query->where($table.'.tenant_id', $user->tenant_id);
            }
        });

        return $next($request);
    }
}
