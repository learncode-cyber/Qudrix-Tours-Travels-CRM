<?php
namespace App\Services\Ai\Adapters;

use App\Models\AiProvider;
use App\Services\Ai\AiCompletionResult;
use App\Services\Ai\AiProviderAdapter;
use App\Services\Ai\AiProviderException;
use Illuminate\Support\Facades\Http;

// Anthropic Messages API adapter.
//
// Implements the documented public contract (POST /v1/messages with the
// x-api-key and anthropic-version headers). Nothing here is invented; the
// operator supplies their own API key. Model IDs are operator-configured —
// note that Anthropic model IDs are complete as-is and must NOT carry a
// date suffix (e.g. 'claude-opus-5', never 'claude-opus-5-20260101').
class AnthropicAdapter implements AiProviderAdapter
{
    private const DEFAULT_BASE_URL = 'https://api.anthropic.com';
    private const API_VERSION = '2023-06-01';

    public function complete(AiProvider $provider, array $messages, ?string $system, array $options = []): AiCompletionResult
    {
        $credentials = $provider->credentials ?? [];
        $apiKey = $credentials['api_key'] ?? null;
        if (!$apiKey) {
            throw new AiProviderException('Anthropic provider has no api_key configured.');
        }

        $body = [
            'model' => $provider->model,
            // max_tokens is required by this API.
            'max_tokens' => $options['max_tokens'] ?? $provider->max_output_tokens ?? 4096,
            'messages' => array_map(
                fn ($m) => ['role' => $m['role'], 'content' => $m['content']],
                $messages
            ),
        ];

        if ($system) {
            $body['system'] = $system;
        }

        $startedAt = microtime(true);

        $response = Http::timeout($options['timeout'] ?? 120)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ])
            ->post(rtrim($provider->base_url ?: self::DEFAULT_BASE_URL, '/') . '/v1/messages', $body);

        $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);

        if (!$response->successful()) {
            throw new AiProviderException(
                'Anthropic API returned HTTP ' . $response->status() . ': ' . $response->body()
            );
        }

        $json = $response->json();

        // content is an array of polymorphic blocks; only text blocks carry
        // the answer, and a thinking block can legitimately come first.
        $text = '';
        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        return new AiCompletionResult(
            text: $text,
            promptTokens: (int) ($json['usage']['input_tokens'] ?? 0),
            completionTokens: (int) ($json['usage']['output_tokens'] ?? 0),
            model: $json['model'] ?? $provider->model,
            latencyMs: $latencyMs,
            raw: $json,
        );
    }
}
