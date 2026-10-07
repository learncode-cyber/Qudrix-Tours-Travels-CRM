<?php

namespace App\Providers;

use App\Models\ApiKey;
use App\Policies\ApiKeyPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // routes/api-public.php references `throttle:api` and
        // `throttle:admin-api`, but neither limiter was defined anywhere in
        // the delivered project (no AppServiceProvider even existed) —
        // any request hitting those routes would have thrown a
        // "Rate limiter [api] is not defined" exception.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('admin-api', function (Request $request) {
            return Limit::perMinute(120)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });

        // FIX (Phase 15 security audit): AdminController called
        // $this->authorize('admin') with no Gate ever defined for that
        // ability anywhere in the project — those endpoints (database
        // optimize/analyze, backup management) would always throw
        // AuthorizationException, for every user including real admins.
        // Wired to the same Role::permissions check used by
        // RBACMiddleware/User::hasPermission() elsewhere, rather than
        // inventing a separate authorization mechanism.
        Gate::define('admin', function ($user) {
            return $user->hasPermission('admin') || $user->hasPermission('*');
        });

        // FIX (Phase 15 security audit): ApiKeyController called
        // authorize('view'/'update'/'delete', $apiKey) but no
        // ApiKeyPolicy was ever registered — same "always denied"
        // failure mode as above.
        Gate::policy(ApiKey::class, ApiKeyPolicy::class);
    }
}
