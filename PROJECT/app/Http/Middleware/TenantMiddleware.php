<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user ?? null;

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'Tenant not identified'], 401);
        }

        // Tenant context for anything that needs it downstream.
        app()->instance('tenant_id', $user->tenant_id);

        // NOTE: this middleware previously called
        //   Model::addGlobalScope('tenant', fn ($q) => $q->where('tenant_id', $user->tenant_id))
        // on the Eloquent base class. That was removed because it was both
        // broken and dangerous:
        //
        //  1. CROSS-TENANT LEAK under any long-running worker (Octane, queue
        //     workers). addGlobalScope on the base Model is process-wide and
        //     static, and the closure captured the FIRST request's $user —
        //     so every later request in that process was silently filtered
        //     by the wrong tenant's id.
        //  2. It applied `where tenant_id` to every model, including the many
        //     join/pivot tables that have no tenant_id column at all
        //     (quotation_items, role_user, taggables, invoice_items,
        //     ab_variants, visa_checklist_items, ...), producing
        //     "Unknown column 'tenant_id'" SQL errors.
        //
        // Tenant isolation is enforced explicitly instead: every controller
        // scopes its queries with ->where('tenant_id', $request->user->tenant_id),
        // which is per-request, correct for tables without the column, and
        // visible at the call site.

        return $next($request);
    }
}
