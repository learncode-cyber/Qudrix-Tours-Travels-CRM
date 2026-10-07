<?php

namespace App\Services\AI\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAdapter implements AIProviderAdapterInterface
{
    public function complete(string $apiKey, string $model, string $prompt, array $options = []): array
    {
        try {
            $response = Http::timeout(30)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'maxOutputTokens' => $options['max_tokens'] ?? 1000,
                        'temperature' => $options['temperature'] ?? 0.7,
                    ],
                ]);

            if (!$response->successful()) {
                return $this->failure($response->json('error.message') ?? $response->body());
            }

            $data = $response->json();

            return [
                'success' => true,
                'text' => $data['candidates'][0]['content']['parts'][0]['text'] ?? null,
                'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? null,
                'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Gemini adapter failed', ['error' => $e->getMessage()]);
            return $this->failure($e->getMessage());
        }
    }

    protected function failure(string $error): array
    {
        return ['success' => false, 'text' => null, 'prompt_tokens' => null, 'completion_tokens' => null, 'error' => $error];
    }
}
