<?php

return [
    'name' => env('APP_NAME', 'QUDRIX Travel CRM'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    // MASTER_PROJECT_AUDIT.md P1: locales the platform actually ships
    // translations for. app/Http/Middleware/SetLocale.php only honors
    // one of these; anything else falls back through the chain to this
    // default 'locale' above.
    'available_locales' => ['en', 'bn', 'ar'],
    'rtl_locales' => ['ar'],
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
