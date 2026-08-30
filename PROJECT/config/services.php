<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Telegram bot credentials for the notification system (Directive §17).
    // Server-side only — never exposed to the frontend.
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    ],
];
