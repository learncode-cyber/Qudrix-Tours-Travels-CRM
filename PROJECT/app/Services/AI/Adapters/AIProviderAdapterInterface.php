<?php

namespace App\Services\AI\Adapters;

interface AIProviderAdapterInterface
{
    /**
     * @return array{
     *   success: bool,
     *   text: ?string,
     *   prompt_tokens: ?int,
     *   completion_tokens: ?int,
     *   error: ?string,
     * }
     */
    public function complete(string $apiKey, string $model, string $prompt, array $options = []): array;
}
