<?php
return [
    'enable_security_headers' => true,
    'enable_rate_limiting' => true,
    'enable_encryption' => true,
    'enable_csrf_protection' => true,
    'enable_sql_injection_detection' => true,
    'enable_xss_protection' => true,
    'max_login_attempts' => 5,
    'lockout_duration_minutes' => 15,
    'password_min_length' => 12,
    'password_require_special_chars' => true,
    'password_require_uppercase' => true,
    'password_require_numbers' => true,
    'api_key_rotation_days' => 90,
    'session_timeout_minutes' => 30,
    'enable_two_factor_auth' => true,
    'require_https' => true,
    'hsts_max_age' => 31536000,
    'csp_directives' => [
        'default-src' => "'self'",
        'script-src' => "'self' 'unsafe-inline'",
        'style-src' => "'self' 'unsafe-inline'",
        'img-src' => "'self' data: https:",
        'font-src' => "'self'"
    ]
];
