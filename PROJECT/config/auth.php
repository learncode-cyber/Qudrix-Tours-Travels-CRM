<?php

// Minimal auth config so Laravel's built-in 'auth' middleware alias and
// Auth facade have a defined guard/provider to resolve against. The
// application's actual authentication for the main CRM API is the custom
// JwtAuth middleware (see app/Http/Middleware/JwtAuth.php), which
// authenticates via Tymon\JWTAuth directly rather than through this guard
// system. This config exists for the few routes (see
// routes/api-webhooks-advanced.php) that use Laravel's stock 'auth' alias.
return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
