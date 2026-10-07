<?php

namespace App\Services\AI\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicAdapter implements AIProviderAdapterInterface
{
    public function complete(string $apiKey, string $model, string $prompt, array $options = []): array
    {
        try {
            $response = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->timeout(30)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => $options['max_tokens'] ?? 1000,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);

            if (!$response->successful()) {
                return $this->failure($response->json('error.message') ?? $response->body());
            }

            $data = $response->json();

            return [
                'success' => true,
                'text' => $data['content'][0]['text'] ?? null,
                'prompt_tokens' => $data['usage']['input_tokens'] ?? null,
                'completion_tokens' => $data['usage']['output_tokens'] ?? null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Anthropic adapter failed', ['error' => $e->getMessage()]);
            return $this->failure($e->getMessage());
        }
    }

    protected function failure(string $error): array
    {
        return ['success' => false, 'text' => null, 'prompt_tokens' => null, 'completion_tokens' => null, 'error' => $error];
    }
}
