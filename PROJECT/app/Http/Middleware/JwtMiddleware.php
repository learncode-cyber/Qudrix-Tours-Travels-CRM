<?php
namespace App\Http\Middleware;
use Tymon\JWTAuth\Facades\JWTAuth;
use Closure;

class JwtMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return $next($request);
    }
}
