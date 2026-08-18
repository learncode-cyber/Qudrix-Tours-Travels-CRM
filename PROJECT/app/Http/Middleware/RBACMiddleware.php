<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RBACMiddleware
{
    public function handle(Request $request, Closure $next, $permission = null)
    {
        $user = $request->user ?? auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($permission) {
            $roles = $user->roles;
            $hasPermission = false;

            foreach ($roles as $role) {
                if ($role->hasPermission($permission) || $role->hasPermission('*')) {
                    $hasPermission = true;
                    break;
                }
            }

            if (!$hasPermission) {
                return response()->json(['error' => "Permission '{$permission}' denied"], 403);
            }
        }

        return $next($request);
    }
}
