<?php
namespace App\Http\Controllers;

use App\Models\AiProvider;
use App\Models\AiUsageLog;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiProviderException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AiProviderController extends Controller
{
    public function index(Request $request)
    {
        $providers = AiProvider::where('tenant_id', $request->user->tenant_id)
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->get()
            ->map(fn (AiProvider $p) => $p->toArray() + [
                'credentials_configured' => !empty($p->credentials),
                'cost_rates_configured' => $p->input_cost_per_million !== null || $p->output_cost_per_million !== null,
            ]);

        return response()->json(['data' => $providers]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::in(config('ai.supported_providers'))],
            'model' => 'required|string|max:255',
            'base_url' => 'nullable|url|max:2048',
            'credentials' => 'nullable|array',
            'is_default' => 'boolean',
            'priority' => 'nullable|integer|min:0',
            'monthly_cost_limit_usd' => 'nullable|numeric|min:0',
            'input_cost_per_million' => 'nullable|numeric|min:0',
            'output_cost_per_million' => 'nullable|numeric|min:0',
            'max_output_tokens' => 'nullable|integer|min:1',
        ]);

        $provider = DB::transaction(function () use ($request, $validated) {
            if ($validated['is_default'] ?? false) {
                AiProvider::where('tenant_id', $request->user->tenant_id)->update(['is_default' => false]);
            }

            return AiProvider::create([
                'tenant_id' => $request->user->tenant_id,
                'is_active' => false,
                ...$validated,
            ]);
        });

        return response()->json([
            'data' => $provider,
            'message' => 'Provider created. Add credentials and run a connection test before activating.',
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $provider = AiProvider::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        return response()->json(['data' => $provider->toArray() + [
            'credentials_configured' => !empty($provider->credentials),
        ]]);
    }

    public function update(Request $request, $id)
    {
        $provider = AiProvider::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'model' => 'sometimes|string|max:255',
            'base_url' => 'nullable|url|max:2048',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'priority' => 'nullable|integer|min:0',
            'monthly_cost_limit_usd' => 'nullable|numeric|min:0',
            'input_cost_per_million' => 'nullable|numeric|min:0',
            'output_cost_per_million' => 'nullable|numeric|min:0',
            'max_output_tokens' => 'nullable|integer|min:1',
        ]);

        // Activating a provider with no credentials would fail on the first
        // real call — refuse here instead, where the admin can see why.
        if (($validated['is_active'] ?? false) && empty($provider->credentials)) {
            return response()->json([
                'error' => 'Add API credentials before activating this provider.',
            ], 422);
        }

        DB::transaction(function () use ($request, $provider, $validated) {
            if ($validated['is_default'] ?? false) {
                AiProvider::where('tenant_id', $request->user->tenant_id)
                    ->where('id', '!=', $provider->id)
                    ->update(['is_default' => false]);
            }
            $provider->update($validated);
        });

        return response()->json(['data' => $provider->fresh()]);
    }

    // Credentials are written only here and never returned by any endpoint:
    // the model encrypts them at rest and hides them from serialization, so
    // they can never reach frontend JavaScript (Directive S4).
    public function updateCredentials(Request $request, $id)
    {
        $provider = AiProvider::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'credentials' => 'required|array',
            'credentials.api_key' => 'required|string',
        ]);

        $provider->update(['credentials' => $validated['credentials']]);

        return response()->json(['message' => 'Credentials saved (encrypted at rest).']);
    }

    public function destroy(Request $request, $id)
    {
        $provider = AiProvider::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $provider->delete();

        return response()->json(['message' => 'Provider deleted']);
    }

    // Issues a real minimal completion against the provider and records the
    // actual outcome — never a fabricated "connected".
    public function test(Request $request, $id, AiGateway $gateway)
    {
        $provider = AiProvider::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        try {
            $adapter = $gateway->adapterFor($provider);
            $result = $adapter->complete(
                $provider,
                [['role' => 'user', 'content' => 'Reply with the single word: OK']],
                null,
                ['max_tokens' => 16, 'timeout' => 30],
            );

            $provider->update(['last_test_at' => now(), 'last_test_error' => null]);

            return response()->json(['data' => [
                'ok' => true,
                'reply' => $result->text,
                'latency_ms' => $result->latencyMs,
                'prompt_tokens' => $result->promptTokens,
                'completion_tokens' => $result->completionTokens,
            ]]);
        } catch (AiProviderException|\Throwable $e) {
            $provider->update(['last_test_at' => now(), 'last_test_error' => $e->getMessage()]);

            return response()->json(['data' => ['ok' => false, 'error' => $e->getMessage()]], 502);
        }
    }

    public function usage(Request $request)
    {
        $tenantId = $request->user->tenant_id;
        $since = $request->since ? now()->parse($request->since) : now()->startOfMonth();

        $rows = AiUsageLog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->selectRaw('ai_provider_id, feature, status, COUNT(*) as calls, SUM(prompt_tokens) as prompt_tokens, SUM(completion_tokens) as completion_tokens, SUM(cost_usd) as cost_usd, AVG(latency_ms) as avg_latency_ms')
            ->groupBy('ai_provider_id', 'feature', 'status')
            ->get();

        $providersMissingRates = AiProvider::where('tenant_id', $tenantId)
            ->whereNull('input_cost_per_million')
            ->whereNull('output_cost_per_million')
            ->pluck('id');

        return response()->json(['data' => [
            'since' => $since->toIso8601String(),
            'breakdown' => $rows,
            'total_cost_usd' => round((float) $rows->sum('cost_usd'), 4),
            'total_calls' => (int) $rows->sum('calls'),
            // Cost figures exclude any provider whose per-token rates the
            // operator has not entered — flagged rather than guessed at.
            'providers_without_cost_rates' => $providersMissingRates,
        ]]);
    }
}
