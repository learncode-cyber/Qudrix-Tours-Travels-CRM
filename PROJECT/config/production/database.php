<?php
return [
    'optimization' => [
        'enable_query_caching' => true,
        'enable_connection_pooling' => true,
        'max_connections' => 100,
        'connection_timeout' => 30,
        'idle_timeout' => 300
    ],
    'backup' => [
        'daily_backup' => true,
        'backup_time' => '02:00',
        'retention_days' => 30,
        'incremental_backup' => true,
        'backup_location' => 's3://qudrix-backups'
    ],
    'replication' => [
        'enable_master_slave' => true,
        'slave_lag_threshold' => 5,
        'auto_failover' => true
    ],
    'monitoring' => [
        'enable_slow_query_log' => true,
        'slow_query_threshold_ms' => 100,
        'monitor_connections' => true,
        'monitor_locks' => true
    ]
];
