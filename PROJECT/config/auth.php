<?php

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'api'),
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // routes/api-public.php uses `middleware(['auth:api', ...])`.
        // tymon/jwt-auth registers a 'jwt' guard driver via its service
        // provider — this wires that up to the User model.
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],

        // routes/api-webhooks-advanced.php uses `middleware(['auth:sanctum', ...])`
        // but laravel/sanctum was not in composer.json at all, so this guard
        // could not have existed. Added the package (composer.json) and this
        // guard together — run `composer update` to actually pull it in.
        'sanctum' => [
            'driver' => 'sanctum',
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
