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
        // FIX (Phase 18 verification): routes/api-public.php,
        // routes/api-webhooks-advanced.php and routes/api-webhooks-monitoring.php
        // existed with fully-implemented controllers, tests and their own
        // prefixes/middleware, but were never registered anywhere — the `api:`
        // parameter above only loads routes/api.php. Every public package/
        // booking/quotation endpoint, admin API-key management endpoint, and
        // all webhook admin/analytics/monitoring endpoints were completely
        // unreachable (404) despite being fully built and audited in earlier
        // phases. `then` runs with no implicit prefix/middleware group, so
        // each file's own explicit `Route::prefix()->middleware()` wrapping
        // is preserved exactly as written (requiring them from inside
        // routes/api.php instead would silently double the `/api` prefix,
        // e.g. turning `admin/api/webhooks-advanced` into
        // `/api/admin/api/webhooks-advanced`).
        then: function () {
            require __DIR__.'/../routes/api-public.php';
            require __DIR__.'/../routes/api-webhooks-advanced.php';
            require __DIR__.'/../routes/api-webhooks-monitoring.php';
        },
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
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'encryption' => \App\Http\Middleware\EncryptionMiddleware::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
            'webhook.tenant' => \App\Http\Middleware\EnsureWebhookBelongsToTenant::class,
        ]);

        // MASTER_PROJECT_AUDIT.md P1 (i18n): applied globally to every API
        // request rather than added route-by-route, so no route file can
        // forget it — matches how routes/api-public.php etc. are all
        // reached via the same `api:` boot path.
        $middleware->api(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        // routes/api-public.php uses the 'auth:api' guard middleware directly
        // (Laravel's built-in `auth` middleware with the `api` guard defined
        // in config/auth.php), so no alias needed for that one.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
