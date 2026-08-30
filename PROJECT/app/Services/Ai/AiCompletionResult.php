<?php
namespace App\Services\Ai;

// Normalized result shape every provider adapter returns, so callers never
// depend on a specific vendor's response format.
class AiCompletionResult
{
    public function __construct(
        public readonly string $text,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly string $model,
        public readonly int $latencyMs,
        public readonly array $raw = [],
    ) {
    }
}
