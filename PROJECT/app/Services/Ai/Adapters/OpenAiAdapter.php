<?php
namespace App\Services\Ai\Adapters;

use App\Models\AiProvider;
use App\Services\Ai\AiCompletionResult;
use App\Services\Ai\AiProviderAdapter;
use App\Services\Ai\AiProviderException;
use Illuminate\Support\Facades\Http;

// OpenAI Chat Completions adapter (POST /v1/chat/completions, Bearer auth).
// base_url is overridable so any OpenAI-compatible endpoint the operator
// has a contract for (Azure OpenAI, a self-hosted gateway, a compatible
// vendor) works through this same adapter without new code.
class OpenAiAdapter implements AiProviderAdapter
{
    private const DEFAULT_BASE_URL = 'https://api.openai.com';

    public function complete(AiProvider $provider, array $messages, ?string $system, array $options = []): AiCompletionResult
    {
        $credentials = $provider->credentials ?? [];
        $apiKey = $credentials['api_key'] ?? null;
        if (!$apiKey) {
            throw new AiProviderException('OpenAI provider has no api_key configured.');
        }

        // This API carries the system prompt as the first message rather
        // than a separate top-level field.
        $payloadMessages = [];
        if ($system) {
            $payloadMessages[] = ['role' => 'system', 'content' => $system];
        }
        foreach ($messages as $m) {
            $payloadMessages[] = ['role' => $m['role'], 'content' => $m['content']];
        }

        $startedAt = microtime(true);

        $response = Http::timeout($options['timeout'] ?? 120)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'content-type' => 'application/json',
            ])
            ->post(rtrim($provider->base_url ?: self::DEFAULT_BASE_URL, '/') . '/v1/chat/completions', [
                'model' => $provider->model,
                'messages' => $payloadMessages,
                'max_completion_tokens' => $options['max_tokens'] ?? $provider->max_output_tokens ?? 4096,
            ]);

        $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);

        if (!$response->successful()) {
            throw new AiProviderException(
                'OpenAI API returned HTTP ' . $response->status() . ': ' . $response->body()
            );
        }

        $json = $response->json();

        return new AiCompletionResult(
            text: (string) ($json['choices'][0]['message']['content'] ?? ''),
            promptTokens: (int) ($json['usage']['prompt_tokens'] ?? 0),
            completionTokens: (int) ($json['usage']['completion_tokens'] ?? 0),
            model: $json['model'] ?? $provider->model,
            latencyMs: $latencyMs,
            raw: $json,
        );
    }
}
