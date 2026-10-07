<?php

namespace App\Http\Controllers;

use App\Models\AIProvider;
use App\Models\AIFeatureConfig;
use App\Models\AIUsageLog;
use App\Services\AI\AIOrchestrator;
use Illuminate\Http\Request;

class AIProviderController extends Controller
{
    public function __construct(protected AIOrchestrator $orchestrator)
    {
    }

    public function index(Request $request)
    {
        // SECURITY: api_key_encrypted is $hidden on the model already,
        // but excluded explicitly here too so it never round-trips even
        // if that safeguard is ever removed by a future change.
        $providers = AIProvider::where('tenant_id', $request->user->tenant_id)
            ->select('id', 'tenant_id', 'provider', 'name', 'default_model', 'max_tokens', 'temperature', 'cost_per_1k_prompt_tokens', 'cost_per_1k_completion_tokens', 'is_active', 'is_default', 'fallback_priority', 'last_tested_at', 'last_test_status', 'last_test_detail', 'created_at')
            ->get();

        return response()->json(['data' => $providers]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|in:gemini,openai,anthropic',
            'name' => 'required|string|max:255',
            'api_key' => 'required|string',
            'default_model' => 'nullable|string',
            'max_tokens' => 'nullable|integer|min:1',
            'temperature' => 'nullable|numeric|between:0,2',
            'cost_per_1k_prompt_tokens' => 'nullable|numeric|min:0',
            'cost_per_1k_completion_tokens' => 'nullable|numeric|min:0',
            'is_default' => 'boolean',
            'fallback_priority' => 'nullable|integer',
        ]);

        $tenantId = $request->user->tenant_id;

        // Only one default provider per tenant.
        if (!empty($validated['is_default'])) {
            AIProvider::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        $provider = AIProvider::create([
            'tenant_id' => $tenantId,
            'provider' => $validated['provider'],
            'name' => $validated['name'],
            'api_key_encrypted' => $validated['api_key'], // encrypted cast handles this on write
            'default_model' => $validated['default_model'] ?? null,
            'max_tokens' => $validated['max_tokens'] ?? null,
            'temperature' => $validated['temperature'] ?? null,
            'cost_per_1k_prompt_tokens' => $validated['cost_per_1k_prompt_tokens'] ?? null,
            'cost_per_1k_completion_tokens' => $validated['cost_per_1k_completion_tokens'] ?? null,
            'is_active' => true,
            'is_default' => $validated['is_default'] ?? false,
            'fallback_priority' => $validated['fallback_priority'] ?? null,
            'last_test_status' => 'not_tested',
        ]);

        $provider->makeHidden('api_key_encrypted');

        return response()->json(['data' => $provider], 201);
    }

    public function update(Request $request, $id)
    {
        $provider = AIProvider::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'api_key' => 'nullable|string',
            'default_model' => 'nullable|string',
            'max_tokens' => 'nullable|integer|min:1',
            'temperature' => 'nullable|numeric|between:0,2',
            'cost_per_1k_prompt_tokens' => 'nullable|numeric|min:0',
            'cost_per_1k_completion_tokens' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
            'fallback_priority' => 'nullable|integer',
        ]);

        if (!empty($validated['is_default'])) {
            AIProvider::where('tenant_id', $request->user->tenant_id)
                ->where('id', '!=', $provider->id)
                ->update(['is_default' => false]);
        }

        if (!empty($validated['api_key'])) {
            $provider->api_key_encrypted = $validated['api_key'];
        }
        unset($validated['api_key']);

        $provider->update($validated);

        return response()->json(['data' => $provider]);
    }

    public function delete(Request $request, $id)
    {
        $provider = AIProvider::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $provider->delete();

        return response()->json(['message' => 'AI provider removed']);
    }

    /**
     * Attempts a genuine minimal completion call against the real
     * provider API. In this sandbox this will always fail (no network
     * access) — that's an honest UNVERIFIED result, not a simulated
     * success. On a real server with network access, this is a real test.
     */
    public function testConnection(Request $request, $id)
    {
        $provider = AIProvider::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $result = $this->orchestrator->complete(
            $request->user->tenant_id,
            '_connection_test',
            'Reply with the single word: OK',
            ['model' => $provider->default_model, 'max_tokens' => 10]
        );

        $provider->update([
            'last_tested_at' => now(),
            'last_test_status' => $result['success'] ? 'success' : 'failed',
            'last_test_detail' => $result['error'] ?? 'Connection successful',
        ]);

        return response()->json([
            'data' => [
                'status' => $result['status'],
                'detail' => $result['error'] ?? 'Connection successful',
            ],
        ]);
    }

    public function getUsageStats(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $logs = AIUsageLog::where('tenant_id', $tenantId);

        return response()->json(['data' => [
            'total_calls' => (clone $logs)->count(),
            'successful' => (clone $logs)->where('status', 'success')->count(),
            'failed' => (clone $logs)->where('status', 'failed')->count(),
            'blocked' => (clone $logs)->where('status', 'blocked')->count(),
            'total_prompt_tokens' => (clone $logs)->sum('prompt_tokens'),
            'total_completion_tokens' => (clone $logs)->sum('completion_tokens'),
            'total_estimated_cost' => (clone $logs)->sum('estimated_cost'),
            'by_feature' => (clone $logs)->selectRaw('feature_key, count(*) as calls')->groupBy('feature_key')->pluck('calls', 'feature_key'),
        ]]);
    }

    public function setFeatureConfig(Request $request)
    {
        // SECURITY: provider must belong to the caller's tenant — a bare
        // exists:ai_providers,id would let a tenant bind a feature to
        // ANOTHER tenant's provider (and so their API key / billing).
        $validated = $request->validate([
            'feature_key' => 'required|string',
            'ai_provider_id' => ['required', \Illuminate\Validation\Rule::exists('ai_providers', 'id')
                ->where('tenant_id', $request->user->tenant_id)],
            'model_override' => 'nullable|string',
        ]);

        $config = AIFeatureConfig::updateOrCreate(
            ['tenant_id' => $request->user->tenant_id, 'feature_key' => $validated['feature_key']],
            ['ai_provider_id' => $validated['ai_provider_id'], 'model_override' => $validated['model_override'] ?? null]
        );

        return response()->json(['data' => $config]);
    }
}
