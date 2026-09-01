<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtAuth
{
    public function handle(Request $request, Closure $next)
    {
        try {
            if (!$token = JWTAuth::getToken()) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            if (!$user = JWTAuth::authenticate($token)) {
                return response()->json(['error' => 'User not found'], 401);
            }

            if (!$user->isActive()) {
                return response()->json(['error' => 'User account is inactive'], 403);
            }

            $request->user = $user;

        } catch (JWTException $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        return $next($request);
    }
}
