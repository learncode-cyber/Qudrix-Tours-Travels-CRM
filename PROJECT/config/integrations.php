<?php

return [
    // Operator-configured API connectors point at arbitrary URLs. Allowing
    // private/loopback addresses turns that feature into an SSRF vector
    // against the host's own network, so it is refused by default. Enable
    // only for a self-hosted deployment that genuinely needs to reach a
    // provider on the internal network.
    'allow_private_network_connectors' => (bool) env('ALLOW_PRIVATE_NETWORK_CONNECTORS', false),

    // Hard cap on how long any single outbound connector call may take,
    // regardless of the per-connector timeout an operator configures.
    'max_timeout_seconds' => (int) env('CONNECTOR_MAX_TIMEOUT_SECONDS', 60),

    // Response bodies larger than this are truncated before being written
    // to api_connector_call_logs, so one chatty provider cannot fill the
    // database.
    'max_logged_response_bytes' => (int) env('CONNECTOR_MAX_LOGGED_RESPONSE_BYTES', 65536),
];
