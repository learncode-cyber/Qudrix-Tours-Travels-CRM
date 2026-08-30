<?php
namespace App\Services;

use App\Models\ApiConnector;
use App\Models\ApiConnectorCallLog;
use App\Models\ApiConnectorEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Executes an operator-configured external API call.
//
// This is the engine behind the Integration Manager: the operator supplies
// their own provider contract (base URL, auth, per-operation path, request
// shape and response mapping) and this service runs it. No third-party
// endpoint is hardcoded or invented anywhere in this codebase — a connector
// without a mapped endpoint reports CONTRACT REQUIRED instead of pretending
// to work.
class ApiConnectorService
{
    /**
     * @throws ConnectorException
     */
    public function execute(ApiConnector $connector, string $operation, array $params = [], ?int $userId = null): array
    {
        if (!$connector->is_active) {
            throw new ConnectorException("Connector '{$connector->name}' is not active.");
        }

        $endpoint = $connector->endpointFor($operation);
        if (!$endpoint) {
            throw new ConnectorException(
                "CONTRACT REQUIRED: connector '{$connector->name}' has no active '{$operation}' endpoint mapped. "
                . 'Configure the provider contract before calling this operation.'
            );
        }

        $url = $this->buildUrl($connector, $endpoint, $params);
        $this->guardAgainstPrivateNetwork($url);

        // Two renders: the real payload (credentials substituted) is sent,
        // the redacted one (credential placeholders left intact) is logged,
        // so secrets never reach api_connector_call_logs.
        $realBody = $this->render($endpoint->request_template ?? [], $params, $connector->credentials ?? []);
        $loggedBody = $this->render($endpoint->request_template ?? [], $params, []);
        $query = $this->render($endpoint->query_template ?? [], $params, $connector->credentials ?? []);

        $timeout = min(
            $connector->timeout_seconds ?: 30,
            (int) config('integrations.max_timeout_seconds', 60)
        );

        $startedAt = microtime(true);
        $status = null;
        $rawBody = null;
        $success = false;
        $error = null;

        try {
            $request = Http::timeout($timeout)
                ->withHeaders($this->buildHeaders($connector))
                ->acceptJson();

            $method = strtoupper($endpoint->http_method ?: 'POST');
            $response = match ($method) {
                'GET' => $request->get($url, $query),
                'DELETE' => $request->delete($url, $realBody),
                'PUT' => $request->put($url, $realBody),
                'PATCH' => $request->patch($url, $realBody),
                default => $request->post($url, $realBody),
            };

            $status = $response->status();
            $rawBody = $response->body();
            $success = $response->successful();

            if (!$success) {
                $error = "Provider returned HTTP {$status}";
            }

            $decoded = $response->json() ?? [];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $decoded = [];
            Log::warning('API connector call failed', [
                'connector_id' => $connector->id,
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
        }

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        ApiConnectorCallLog::create([
            'tenant_id' => $connector->tenant_id,
            'api_connector_id' => $connector->id,
            'user_id' => $userId,
            'operation' => $operation,
            'http_method' => strtoupper($endpoint->http_method ?: 'POST'),
            'url' => $url,
            'request_payload' => $loggedBody,
            'response_status' => $status,
            'response_body' => $rawBody === null
                ? null
                : mb_strcut($rawBody, 0, (int) config('integrations.max_logged_response_bytes', 65536)),
            'duration_ms' => $durationMs,
            'success' => $success,
            'error_message' => $error,
            'created_at' => now(),
        ]);

        if (!$success) {
            throw new ConnectorException($error ?? 'Connector call failed', $status ?? 0);
        }

        return [
            'raw' => $decoded,
            'mapped' => $this->mapResponse($endpoint, $decoded),
            'duration_ms' => $durationMs,
            'status' => $status,
        ];
    }

    // A cheap reachability check: issues the connector's own 'status'
    // operation if one is mapped, otherwise a bare GET on the base URL.
    // Records the real outcome on the connector — never a fake "connected".
    public function testConnection(ApiConnector $connector, ?int $userId = null): array
    {
        try {
            if ($connector->endpointFor('status')) {
                $this->execute($connector, 'status', [], $userId);
            } else {
                $this->guardAgainstPrivateNetwork($connector->base_url);
                $response = Http::timeout(min($connector->timeout_seconds ?: 30, (int) config('integrations.max_timeout_seconds', 60)))
                    ->withHeaders($this->buildHeaders($connector))
                    ->get($connector->base_url);
                if (!$response->successful()) {
                    throw new ConnectorException("Base URL returned HTTP {$response->status()}");
                }
            }

            $connector->update([
                'status' => 'connected',
                'last_test_at' => now(),
                'last_test_error' => null,
            ]);

            return ['connected' => true];
        } catch (\Throwable $e) {
            $connector->update([
                'status' => 'failed',
                'last_test_at' => now(),
                'last_test_error' => $e->getMessage(),
            ]);

            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildUrl(ApiConnector $connector, ApiConnectorEndpoint $endpoint, array $params): string
    {
        $path = $endpoint->path;
        foreach ($params as $key => $value) {
            if (is_scalar($value)) {
                $path = str_replace('{' . $key . '}', rawurlencode((string) $value), $path);
            }
        }

        return rtrim($connector->base_url, '/') . '/' . ltrim($path, '/');
    }

    private function buildHeaders(ApiConnector $connector): array
    {
        $headers = $connector->default_headers ?? [];
        $credentials = $connector->credentials ?? [];

        switch ($connector->auth_type) {
            case 'bearer':
                if (!empty($credentials['token'])) {
                    $headers['Authorization'] = 'Bearer ' . $credentials['token'];
                }
                break;
            case 'api_key_header':
                if (!empty($credentials['api_key']) && $connector->auth_key_name) {
                    $headers[$connector->auth_key_name] = $credentials['api_key'];
                }
                break;
            case 'basic':
                if (isset($credentials['username'], $credentials['password'])) {
                    $headers['Authorization'] = 'Basic ' . base64_encode($credentials['username'] . ':' . $credentials['password']);
                }
                break;
            case 'custom_headers':
                foreach ($credentials as $name => $value) {
                    if (is_string($value)) {
                        $headers[$name] = $value;
                    }
                }
                break;
        }

        return $headers;
    }

    // Recursively substitutes {{param}} and {{credential.KEY}} placeholders.
    // Passing an empty $credentials array leaves credential placeholders
    // intact, which is how the redacted log copy is produced.
    private function render(array $template, array $params, array $credentials): array
    {
        $out = [];
        foreach ($template as $key => $value) {
            if (is_array($value)) {
                $out[$key] = $this->render($value, $params, $credentials);
                continue;
            }
            if (!is_string($value)) {
                $out[$key] = $value;
                continue;
            }

            $out[$key] = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) use ($params, $credentials, $value) {
                $token = $m[1];
                if (str_starts_with($token, 'credential.')) {
                    $credKey = substr($token, strlen('credential.'));
                    return $credentials[$credKey] ?? $m[0];
                }
                $resolved = data_get($params, $token);
                return is_scalar($resolved) ? (string) $resolved : $m[0];
            }, $value);
        }

        return $out;
    }

    // Translates a provider's own response shape into our normalized shape
    // using the operator-supplied mapping. Without a mapping the raw
    // decoded response is returned untouched rather than guessed at.
    private function mapResponse(ApiConnectorEndpoint $endpoint, array $decoded): array
    {
        $mapping = $endpoint->response_mapping ?? [];
        if (empty($mapping)) {
            return $decoded;
        }

        if ($endpoint->response_collection_path) {
            $items = data_get($decoded, $endpoint->response_collection_path, []);
            if (!is_array($items)) {
                return [];
            }
            return array_values(array_map(
                fn ($item) => $this->mapItem($mapping, $item),
                $items
            ));
        }

        return $this->mapItem($mapping, $decoded);
    }

    private function mapItem(array $mapping, $item): array
    {
        $mapped = [];
        foreach ($mapping as $ourField => $providerPath) {
            $mapped[$ourField] = is_string($providerPath) ? data_get($item, $providerPath) : null;
        }
        return $mapped;
    }

    // Operator-supplied URLs are attacker-reachable configuration in a
    // multi-tenant system: without this guard a tenant admin could point a
    // connector at 127.0.0.1 or a cloud metadata endpoint and use the CRM
    // as an SSRF proxy into the host's own network.
    private function guardAgainstPrivateNetwork(string $url): void
    {
        if (config('integrations.allow_private_network_connectors')) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            throw new ConnectorException("Connector URL is malformed: {$url}");
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new ConnectorException("Connector URL scheme '{$scheme}' is not allowed.");
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new ConnectorException(
                "Connector URL resolves to a private or reserved address ({$ip}). "
                . 'Set ALLOW_PRIVATE_NETWORK_CONNECTORS=true only if this is intentional.'
            );
        }
    }
}
