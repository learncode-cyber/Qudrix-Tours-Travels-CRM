<?php

namespace App\Services\AI\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIAdapter implements AIProviderAdapterInterface
{
    public function complete(string $apiKey, string $model, string $prompt, array $options = []): array
    {
        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => $options['max_tokens'] ?? 1000,
                    'temperature' => $options['temperature'] ?? 0.7,
                ]);

            if (!$response->successful()) {
                return $this->failure($response->json('error.message') ?? $response->body());
            }

            $data = $response->json();

            return [
                'success' => true,
                'text' => $data['choices'][0]['message']['content'] ?? null,
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('OpenAI adapter failed', ['error' => $e->getMessage()]);
            return $this->failure($e->getMessage());
        }
    }

    protected function failure(string $error): array
    {
        return ['success' => false, 'text' => null, 'prompt_tokens' => null, 'completion_tokens' => null, 'error' => $error];
    }
}
