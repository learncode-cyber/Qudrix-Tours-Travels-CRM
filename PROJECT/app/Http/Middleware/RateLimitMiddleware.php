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
        return sha1($request->user()->id . '|' . $request->ip());
    }
}
