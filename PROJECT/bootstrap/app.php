<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Aliases referenced throughout routes/api.php, routes/api-public.php, etc.
        // NOTE: these were used in routes but never registered anywhere in the
        // project as delivered — that omission meant the app could not boot.
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtAuth::class,
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
            'tenant.scope' => \App\Http\Middleware\ValidateTenantScope::class,
            'rbac' => \App\Http\Middleware\RBACMiddleware::class,
            'audit' => \App\Http\Middleware\AuditMiddleware::class,
            'rate.limit' => \App\Http\Middleware\RateLimitMiddleware::class,
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
            // routes/api-webhooks-advanced.php calls this 'api.key.auth', not
            // 'api.key' — matching that name exactly (a mismatch here would
            // silently 500 every request through that middleware group).
            'api.key.auth' => \App\Http\Middleware\ApiKeyMiddleware::class,
            'encryption' => \App\Http\Middleware\EncryptionMiddleware::class,
        ]);

        // routes/api-public.php uses the 'auth:api' guard middleware directly
        // (Laravel's built-in `auth` middleware with the `api` guard defined
        // in config/auth.php), so no alias needed for that one.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
