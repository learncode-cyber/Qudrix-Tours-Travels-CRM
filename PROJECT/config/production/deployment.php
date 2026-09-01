<?php
return [
    'environment' => 'production',
    'debug' => false,
    'log_level' => 'warning',
    'enable_query_logging' => false,
    'enable_request_logging' => true,
    'cache_ttl_seconds' => 3600,
    'asset_versioning' => true,
    'enable_gzip_compression' => true,
    'enable_cdn' => true,
    'cdn_url' => env('CDN_URL', 'https://cdn.qudrix.com'),
    'max_upload_size_mb' => 50,
    'enable_virus_scanning' => true,
    'enable_malware_detection' => true,
    'ssl_certificate' => env('SSL_CERT_PATH'),
    'ssl_key' => env('SSL_KEY_PATH'),
    'enable_http2' => true,
    'enable_http3' => true,
    'server_push_assets' => true
];
