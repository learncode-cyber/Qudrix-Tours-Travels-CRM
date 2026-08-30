<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // routes/api.php already defines its own '/v1' sub-prefix, so this
        // param wraps it as the standard /api/v1/... — matching the prefix
        // documented in routes/api-public.php.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // These route files each declare their own full prefix already
            // (e.g. 'admin/api/webhooks-advanced'), so they are required
            // directly rather than run through the api: wrapper, which
            // would double the '/api' segment.
            require __DIR__.'/../routes/api-public.php';
            require __DIR__.'/../routes/api-webhooks-advanced.php';
            require __DIR__.'/../routes/api-webhooks-monitoring.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Aliases for routes/api.php and friends. Names match what the
        // route files already reference — see DOCUMENTATION/GAP_ANALYSIS.md
        // for the ones still pending an architecture decision
        // (auth:sanctum / api guard used by the webhook route files, which
        // are not wired to an actual auth driver yet).
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtAuth::class,
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
            'tenant.scope' => \App\Http\Middleware\ValidateTenantScope::class,
            'audit' => \App\Http\Middleware\AuditMiddleware::class,
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
            'rate.limit' => \App\Http\Middleware\RateLimitMiddleware::class,
            'rbac' => \App\Http\Middleware\RBACMiddleware::class,
            'api.key' => \App\Http\Middleware\ApiKeyMiddleware::class,
            'api.key.auth' => \App\Http\Middleware\ApiKeyMiddleware::class,
            'encryption' => \App\Http\Middleware\EncryptionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
