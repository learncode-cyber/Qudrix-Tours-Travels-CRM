<?php
namespace App\Services\Ai;

use App\Models\AiProvider;
use App\Models\AiUsageLog;
use App\Services\Ai\Adapters\AnthropicAdapter;
use App\Services\Ai\Adapters\GeminiAdapter;
use App\Services\Ai\Adapters\OpenAiAdapter;
use Illuminate\Support\Facades\Log;

// The single entry point for every AI call in the system.
//
// Application code never names a vendor: it asks the gateway for a
// completion and the gateway picks a configured provider by priority,
// enforces spend limits, fails over to the next provider on error, and
// records real usage and cost. This is what makes the AI layer
// provider-independent (Directive S4) rather than provider-flavoured.
class AiGateway
{
    /** Adapter per supported provider key from config('ai.supported_providers'). */
    private const ADAPTERS = [
        'anthropic' => AnthropicAdapter::class,
        'openai' => OpenAiAdapter::class,
        'gemini' => GeminiAdapter::class,
    ];

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @throws AiProviderException when every eligible provider fails
     */
    public function complete(
        int $tenantId,
        string $feature,
        array $messages,
        ?string $system = null,
        array $options = [],
        ?int $userId = null,
    ): AiCompletionResult {
        $providers = $this->eligibleProviders($tenantId);

        if ($providers->isEmpty()) {
            throw new AiProviderException(
                'No active AI provider is configured for this tenant. '
                . 'Configure one under Settings -> AI Providers before using AI features.'
            );
        }

        $failures = [];

        // Failover: try each eligible provider in priority order.
        foreach ($providers as $provider) {
            if ($this->isOverSpendLimit($provider)) {
                $failures[] = "{$provider->provider}/{$provider->model}: monthly cost limit reached";
                continue;
            }

            try {
                $adapter = $this->adapterFor($provider);
                $result = $adapter->complete($provider, $messages, $system, $options);

                $this->logUsage($provider, $feature, $userId, $result, 'success', null);

                return $result;
            } catch (\Throwable $e) {
                $failures[] = "{$provider->provider}/{$provider->model}: {$e->getMessage()}";

                $this->logUsage($provider, $feature, $userId, null, 'error', $e->getMessage());

                Log::warning('AI provider failed, trying next', [
                    'provider_id' => $provider->id,
                    'provider' => $provider->provider,
                    'feature' => $feature,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new AiProviderException(
            'All configured AI providers failed: ' . implode(' | ', $failures)
        );
    }

    public function adapterFor(AiProvider $provider): AiProviderAdapter
    {
        $class = self::ADAPTERS[$provider->provider] ?? null;
        if (!$class) {
            throw new AiProviderException(
                "No adapter is implemented for provider '{$provider->provider}'. "
                . 'Supported: ' . implode(', ', array_keys(self::ADAPTERS))
            );
        }

        return app($class);
    }

    /**
     * Active providers for a tenant, default first, then by priority.
     */
    private function eligibleProviders(int $tenantId)
    {
        return AiProvider::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    // Spend is computed from real logged usage this calendar month, not an
    // estimate. A provider with no configured cost rates has unknown cost
    // and is therefore never blocked by a limit — the limit is reported as
    // unenforceable rather than silently treated as zero spend.
    private function isOverSpendLimit(AiProvider $provider): bool
    {
        if (!$provider->monthly_cost_limit_usd) {
            return false;
        }

        $spentThisMonth = (float) AiUsageLog::where('ai_provider_id', $provider->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost_usd');

        if ($spentThisMonth >= (float) $provider->monthly_cost_limit_usd) {
            return true;
        }

        $globalCeiling = (float) config('ai.global_monthly_cost_ceiling_usd', 0);
        if ($globalCeiling > 0) {
            $tenantSpend = (float) AiUsageLog::where('tenant_id', $provider->tenant_id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('cost_usd');
            if ($tenantSpend >= $globalCeiling) {
                return true;
            }
        }

        return false;
    }

    private function logUsage(
        AiProvider $provider,
        string $feature,
        ?int $userId,
        ?AiCompletionResult $result,
        string $status,
        ?string $error,
    ): void {
        AiUsageLog::create([
            'tenant_id' => $provider->tenant_id,
            'ai_provider_id' => $provider->id,
            'user_id' => $userId,
            'feature' => $feature,
            'prompt_tokens' => $result?->promptTokens ?? 0,
            'completion_tokens' => $result?->completionTokens ?? 0,
            'cost_usd' => $result ? $this->calculateCost($provider, $result) : 0,
            'latency_ms' => $result?->latencyMs,
            'status' => $status,
            'error_message' => $error,
            'created_at' => now(),
        ]);
    }

    // Cost is derived from operator-configured per-million rates. If the
    // operator has not entered rates for this provider, cost is recorded as
    // 0 and the usage report flags the provider as "rates not configured"
    // rather than presenting a fabricated dollar figure.
    private function calculateCost(AiProvider $provider, AiCompletionResult $result): float
    {
        $inputRate = $provider->input_cost_per_million;
        $outputRate = $provider->output_cost_per_million;

        if ($inputRate === null && $outputRate === null) {
            return 0.0;
        }

        return round(
            ($result->promptTokens / 1_000_000) * (float) ($inputRate ?? 0)
            + ($result->completionTokens / 1_000_000) * (float) ($outputRate ?? 0),
            4
        );
    }
}
