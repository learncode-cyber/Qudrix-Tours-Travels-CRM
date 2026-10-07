<?php

namespace App\Services\AI;

use App\Models\AIProvider;
use App\Models\AIFeatureConfig;
use App\Models\AIUsageLog;
use App\Services\AI\Adapters\GeminiAdapter;
use App\Services\AI\Adapters\OpenAIAdapter;
use App\Services\AI\Adapters\AnthropicAdapter;
use App\Services\AI\Adapters\AIProviderAdapterInterface;

/**
 * PHASE 9: central AI dispatch. This is the ONLY place in the
 * application that should call out to an AI provider — every future
 * AI feature (Sales Agent, Package Builder NLU, etc. from later phases)
 * goes through here, so provider/model changes never require touching
 * feature-level code.
 */
class AIOrchestrator
{
    public function complete(int $tenantId, string $featureKey, string $prompt, array $options = []): array
    {
        $provider = $this->resolveProvider($tenantId, $featureKey);

        if (!$provider) {
            $this->logUsage($tenantId, null, $featureKey, null, 'blocked', 'No active AI provider configured for this tenant.');
            return ['success' => false, 'text' => null, 'error' => 'No active AI provider configured for this tenant.', 'status' => 'blocked'];
        }

        $model = $options['model'] ?? $this->modelFor($tenantId, $featureKey, $provider);
        $adapter = $this->adapterFor($provider->provider);

        $result = $adapter->complete($provider->api_key_encrypted, $model, $prompt, $options);

        if ($result['success']) {
            $cost = $this->estimateCost($provider, $result['prompt_tokens'], $result['completion_tokens']);

            $this->logUsage(
                $tenantId, $provider->id, $featureKey, $model,
                'success', null, $result['prompt_tokens'], $result['completion_tokens'], $cost
            );

            return ['success' => true, 'text' => $result['text'], 'error' => null, 'status' => 'success', 'provider' => $provider->provider, 'model' => $model];
        }

        $this->logUsage($tenantId, $provider->id, $featureKey, $model, 'failed', $result['error']);

        // Try the next fallback provider, if one is configured.
        $fallback = AIProvider::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('id', '!=', $provider->id)
            ->whereNotNull('fallback_priority')
            ->orderBy('fallback_priority')
            ->first();

        if ($fallback) {
            $fallbackAdapter = $this->adapterFor($fallback->provider);
            $fallbackModel = $fallback->default_model ?? $model;
            $fallbackResult = $fallbackAdapter->complete($fallback->api_key_encrypted, $fallbackModel, $prompt, $options);

            $status = $fallbackResult['success'] ? 'success' : 'failed';
            $this->logUsage(
                $tenantId, $fallback->id, $featureKey, $fallbackModel, $status,
                $fallbackResult['error'] ?? null, $fallbackResult['prompt_tokens'] ?? null, $fallbackResult['completion_tokens'] ?? null
            );

            if ($fallbackResult['success']) {
                return ['success' => true, 'text' => $fallbackResult['text'], 'error' => null, 'status' => 'success', 'provider' => $fallback->provider, 'model' => $fallbackModel, 'used_fallback' => true];
            }
        }

        return ['success' => false, 'text' => null, 'error' => $result['error'], 'status' => 'failed'];
    }

    protected function resolveProvider(int $tenantId, string $featureKey): ?AIProvider
    {
        $config = AIFeatureConfig::where('tenant_id', $tenantId)
            ->where('feature_key', $featureKey)
            ->first();

        if ($config?->provider && $config->provider->is_active) {
            return $config->provider;
        }

        return AIProvider::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }

    protected function modelFor(int $tenantId, string $featureKey, AIProvider $provider): string
    {
        $config = AIFeatureConfig::where('tenant_id', $tenantId)->where('feature_key', $featureKey)->first();
        return $config?->model_override ?? $provider->default_model ?? 'default';
    }

    protected function adapterFor(string $provider): AIProviderAdapterInterface
    {
        return match ($provider) {
            'gemini' => new GeminiAdapter(),
            'openai' => new OpenAIAdapter(),
            'anthropic' => new AnthropicAdapter(),
            default => throw new \InvalidArgumentException("Unknown AI provider: {$provider}"),
        };
    }

    protected function estimateCost(AIProvider $provider, ?int $promptTokens, ?int $completionTokens): ?float
    {
        if ($provider->cost_per_1k_prompt_tokens === null || $provider->cost_per_1k_completion_tokens === null) {
            return null; // no tenant-configured rate — do not guess
        }

        $promptCost = ($promptTokens ?? 0) / 1000 * (float) $provider->cost_per_1k_prompt_tokens;
        $completionCost = ($completionTokens ?? 0) / 1000 * (float) $provider->cost_per_1k_completion_tokens;

        return round($promptCost + $completionCost, 6);
    }

    protected function logUsage(
        int $tenantId, ?int $providerId, string $featureKey, ?string $model,
        string $status, ?string $error = null, ?int $promptTokens = null,
        ?int $completionTokens = null, ?float $cost = null
    ): void {
        AIUsageLog::create([
            'tenant_id' => $tenantId,
            'ai_provider_id' => $providerId,
            'feature_key' => $featureKey,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'estimated_cost' => $cost,
            'status' => $status,
            'error_detail' => $error,
        ]);
    }
}
