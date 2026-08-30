<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class RateLimitMiddleware
{
    public function __construct(protected RateLimiter $limiter)
    {
    }

    public function handle(Request $request, Closure $next, ?string $maxAttempts = null, ?string $decayMinutes = null)
    {
        $max = (int) ($maxAttempts ?? $this->defaultLimitFor($request));
        $decay = (int) ($decayMinutes ?? 1);

        $key = $this->resolveRequestSignature($request);

        if ($this->limiter->tooManyAttempts($key, $max)) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'message' => 'Too many requests',
                'retry_after_seconds' => $retryAfter,
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $max,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        $this->limiter->hit($key, $decay * 60);

        $response = $next($request);

        if (method_exists($response, 'withHeaders')) {
            $response->withHeaders([
                'X-RateLimit-Limit' => $max,
                'X-RateLimit-Remaining' => max(0, $this->limiter->remaining($key, $max)),
            ]);
        }

        return $response;
    }

    /**
     * Unauthenticated surfaces (public quotation links, the public API)
     * get a tighter default than signed-in staff traffic.
     */
    protected function defaultLimitFor(Request $request): int
    {
        return $this->authenticatedUserId($request) !== null
            ? (int) config('security.rate_limit.authenticated_per_minute', 100)
            : (int) config('security.rate_limit.guest_per_minute', 30);
    }

    /**
     * This application authenticates through the custom JwtAuth middleware,
     * which sets `$request->user` as a PROPERTY. Laravel's `$request->user()`
     * method is never populated (the app never calls Auth::login), so the
     * previous `$request->user()->id` here fataled with "Call to a member
     * function id() on null" on every route that runs this middleware
     * without jwt.auth — which is every public route it is applied to.
     */
    protected function authenticatedUserId(Request $request): ?int
    {
        $user = $request->user ?? null;

        return $user->id ?? null;
    }

    protected function resolveRequestSignature(Request $request): string
    {
        $userId = $this->authenticatedUserId($request);

        // Guests are keyed by IP + route, so one abusive client cannot
        // exhaust another endpoint's budget for everyone.
        return sha1(($userId ?? 'guest') . '|' . $request->ip() . '|' . $request->path());
    }
}
