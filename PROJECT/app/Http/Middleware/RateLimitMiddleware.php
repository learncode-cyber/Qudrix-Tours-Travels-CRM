<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;

class RateLimitMiddleware
{
    protected $limiter;
    
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }
    
    public function handle(Request $request, Closure $next)
    {
        $key = $this->resolveRequestSignature($request);
        $maxAttempts = 100;
        $decayMinutes = 1;
        
        if ($this->limiter->tooManyAttempts($key, $maxAttempts, $decayMinutes)) {
            return response()->json(['message' => 'Too many requests'], 429);
        }
        
        $this->limiter->hit($key, $decayMinutes * 60);
        
        return $next($request);
    }
    
    protected function resolveRequestSignature(Request $request)
    {
        // FIX (Phase 1 audit): JwtAuth middleware sets `$request->user` as a
        // plain property, not via the Auth guard — it never calls
        // Auth::setUser() or login(). Calling $request->user() (the method,
        // which resolves through the guard) returns null here, so ->id
        // would fatal with "Attempt to read property on null" on every
        // authenticated request. Every other middleware in this stack
        // (Tenant, RBAC, Audit) correctly reads the ->user property instead.
        $user = $request->user ?? $request->user();

        return sha1(($user->id ?? 'guest') . '|' . $request->ip());
    }
}
