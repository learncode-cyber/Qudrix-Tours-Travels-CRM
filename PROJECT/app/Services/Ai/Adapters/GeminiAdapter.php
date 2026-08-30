<?php
namespace App\Services\Ai\Adapters;

use App\Models\AiProvider;
use App\Services\Ai\AiCompletionResult;
use App\Services\Ai\AiProviderAdapter;
use App\Services\Ai\AiProviderException;
use Illuminate\Support\Facades\Http;

// Google Gemini generateContent adapter.
// Uses this API's own shapes: 'contents' with per-part text, 'model' role
// instead of 'assistant', and a separate systemInstruction field.
class GeminiAdapter implements AiProviderAdapter
{
    private const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com';

    public function complete(AiProvider $provider, array $messages, ?string $system, array $options = []): AiCompletionResult
    {
        $credentials = $provider->credentials ?? [];
        $apiKey = $credentials['api_key'] ?? null;
        if (!$apiKey) {
            throw new AiProviderException('Gemini provider has no api_key configured.');
        }

        $contents = array_map(fn ($m) => [
            // This API names the assistant role 'model'.
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $messages);

        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? $provider->max_output_tokens ?? 4096,
            ],
        ];

        if ($system) {
            $body['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        $url = rtrim($provider->base_url ?: self::DEFAULT_BASE_URL, '/')
            . '/v1beta/models/' . $provider->model . ':generateContent';

        $startedAt = microtime(true);

        $response = Http::timeout($options['timeout'] ?? 120)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'content-type' => 'application/json',
            ])
            ->post($url, $body);

        $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);

        if (!$response->successful()) {
            throw new AiProviderException(
                'Gemini API returned HTTP ' . $response->status() . ': ' . $response->body()
            );
        }

        $json = $response->json();

        $text = '';
        foreach ($json['candidates'][0]['content']['parts'] ?? [] as $part) {
            $text .= $part['text'] ?? '';
        }

        return new AiCompletionResult(
            text: $text,
            promptTokens: (int) ($json['usageMetadata']['promptTokenCount'] ?? 0),
            completionTokens: (int) ($json['usageMetadata']['candidatesTokenCount'] ?? 0),
            model: $provider->model,
            latencyMs: $latencyMs,
            raw: $json,
        );
    }
}
