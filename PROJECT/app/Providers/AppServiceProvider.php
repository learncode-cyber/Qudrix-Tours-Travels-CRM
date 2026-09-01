<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
    }
}
