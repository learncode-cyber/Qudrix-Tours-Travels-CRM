<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * PHASE 16 SECURITY AUDIT FINDING: Admin\IntegrationController's
 * constructor called $this->middleware('role:super-admin,admin'), but
 * no 'role' middleware alias was ever registered anywhere in the
 * project. This controller (website integration management:
 * credentials, test-connection, audit logs) would have fatal-errored
 * on every single request, regardless of the caller's actual role.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = auth()->user() ?? $request->user;

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $userRoleNames = $user->roles()->pluck('name')->toArray();

        if (empty(array_intersect($roles, $userRoleNames))) {
            return response()->json(['error' => 'Insufficient role privileges'], 403);
        }

        return $next($request);
    }
}
