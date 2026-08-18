<?php
return [
    'concurrent_users' => [100, 500, 1000, 5000],
    'test_duration_seconds' => 300,
    'ramp_up_time' => 60,
    'endpoints' => [
        '/api/v1/health',
        '/api/v1/bookings',
        '/api/v1/customers',
        '/api/v1/reports',
        '/api/v1/dashboard/kpi',
        '/api/v1/analytics/metrics',
        '/api/v1/offline/data',
        '/api/v1/cache/stats'
    ],
    'thresholds' => [
        'response_time_p99' => 500,     // 500ms for 99th percentile
        'response_time_p95' => 250,     // 250ms for 95th percentile
        'error_rate' => 0.01,           // 1% error rate max
        'throughput_min' => 100         // Minimum 100 req/sec
    ],
    'stress_test' => [
        'peak_users' => 10000,
        'sustained_time' => 600,
        'cooldown_time' => 300
    ]
];
