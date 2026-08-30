<?php

return [
    'rate_limit' => [
        'authenticated_per_minute' => (int) env('RATE_LIMIT_AUTHENTICATED', 100),
        // Unauthenticated surfaces (public quotation links, public API) get
        // a tighter budget than signed-in staff traffic.
        'guest_per_minute' => (int) env('RATE_LIMIT_GUEST', 30),
        'login_attempts_per_minute' => (int) env('RATE_LIMIT_LOGIN', 5),
    ],

    'login' => [
        // After this many consecutive failures for one email+IP pair,
        // further attempts are refused for the lockout window. This blunts
        // credential stuffing without letting an attacker lock a victim out
        // globally, since the key includes the source IP.
        'max_failed_attempts' => (int) env('LOGIN_MAX_FAILED_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
    ],

    'access_log' => [
        'enabled' => (bool) env('ACCESS_LOG_ENABLED', true),
        // Paths that are noisy and carry no security value.
        'ignore_paths' => ['api/v1/health', 'up'],
        // Never persist these request fields, even when logging bodies.
        'redact_keys' => [
            'password', 'password_confirmation', 'current_password',
            'token', 'api_key', 'api_secret', 'secret', 'credentials',
            'authorization', 'x-api-secret',
        ],
        // Requests slower than this are flagged for review.
        'slow_request_ms' => (int) env('ACCESS_LOG_SLOW_MS', 3000),
    ],
];
