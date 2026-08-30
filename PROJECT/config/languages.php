<?php

return [
    // Locales the CRM UI and translatable content support. Adding a locale
    // here does not add UI strings by itself — the frontend i18n bundles and
    // Translation rows for each entity still need to be populated.
    'available' => ['en', 'bn', 'ar'],

    'default' => env('DEFAULT_LOCALE', 'en'),

    // Locales that render right-to-left.
    'rtl' => ['ar'],
];
