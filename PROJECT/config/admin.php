<?php

return [
    // The admin URL segment used for admin-only routes. Changing this env
    // value moves the admin path without touching auth, RBAC, or route
    // definitions — they all read from config('admin.path').
    'path' => env('ADMIN_URL_PATH', 'admin'),
];
