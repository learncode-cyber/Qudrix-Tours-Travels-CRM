<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\AiUsageLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase9AiProviderTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $otherTenant;
    private $user;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'AI Tenant',
            'slug' => 'ai-tenant',
            'is_active' => true,
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-p9',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'AI User',
            'email' => 'ai@example.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->token = JWTAuth::fromUser($this->user);
    }

    private function auth()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    private function makeProvider(array $overrides = []): AiProvider
    {
        return AiProvider::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-5',
            'is_active' => false,
            'priority' => 1,
        ], $overrides));
    }

    // --- CRUD + credential hiding ---

    public function test_provider_create_hides_credentials_and_rejects_unsupported_provider()
    {
        $response = $this->auth()->postJson('/api/v1/ai-providers', [
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-5',
            'credentials' => ['api_key' => 'sk-real-secret'],
        ]);
        $response->assertStatus(201)->assertJsonMissingPath('data.credentials');
        $this->assertStringNotContainsString('sk-real-secret', $response->getContent());

        $invalid = $this->auth()->postJson('/api/v1/ai-providers', [
            'provider' => 'made_up_vendor',
            'model' => 'x',
        ]);
        $invalid->assertStatus(422);
    }

    public function test_index_reports_credentials_and_cost_rate_flags_without_leaking_values()
    {
        $this->makeProvider(['credentials' => ['api_key' => 'sk-secret'], 'input_cost_per_million' => 3]);

        $response = $this->auth()->getJson('/api/v1/ai-providers');
        $response->assertOk()
            ->assertJsonPath('data.0.credentials_configured', true)
            ->assertJsonPath('data.0.cost_rates_configured', true);
        $this->assertStringNotContainsString('sk-secret', $response->getContent());
    }

    public function test_providers_are_tenant_scoped()
    {
        $mine = $this->makeProvider();
        $this->makeProvider(['tenant_id' => $this->otherTenant->id]);

        $response = $this->auth()->getJson('/api/v1/ai-providers');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($mine->id, $response->json('data.0.id'));
    }

    public function test_only_one_default_provider_per_tenant()
    {
        $first = $this->makeProvider(['is_default' => true]);

        $this->auth()->postJson('/api/v1/ai-providers', [
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'is_default' => true,
        ])->assertStatus(201);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertEquals(1, AiProvider::where('tenant_id', $this->tenant->id)->where('is_default', true)->count());
    }

    // --- Activation guard ---

    public function test_activation_is_refused_without_credentials()
    {
        $provider = $this->makeProvider();

        $response = $this->auth()->putJson("/api/v1/ai-providers/{$provider->id}", ['is_active' => true]);

        $response->assertStatus(422)->assertJsonPath('error', 'Add API credentials before activating this provider.');
        $this->assertFalse($provider->fresh()->is_active);
    }

    public function test_activation_succeeds_once_credentials_are_set()
    {
        $provider = $this->makeProvider();
        $this->auth()->putJson("/api/v1/ai-providers/{$provider->id}/credentials", [
            'credentials' => ['api_key' => 'sk-real'],
        ])->assertOk();

        $response = $this->auth()->putJson("/api/v1/ai-providers/{$provider->id}", ['is_active' => true]);

        $response->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_credentials_endpoint_requires_api_key()
    {
        $provider = $this->makeProvider();

        $response = $this->auth()->putJson("/api/v1/ai-providers/{$provider->id}/credentials", [
            'credentials' => ['not_api_key' => 'x'],
        ]);

        $response->assertStatus(422);
    }

    // --- Test-connection honesty (via Http::fake, no real network) ---

    public function test_test_connection_reports_real_success()
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'OK']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 2],
                'model' => 'claude-sonnet-5',
            ], 200),
        ]);

        $provider = $this->makeProvider(['credentials' => ['api_key' => 'sk-real']]);

        $response = $this->auth()->postJson("/api/v1/ai-providers/{$provider->id}/test");

        $response->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.reply', 'OK');
        $this->assertNotNull($provider->fresh()->last_test_at);
        $this->assertNull($provider->fresh()->last_test_error);
    }

    public function test_test_connection_reports_real_failure_honestly()
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => ['message' => 'invalid key']], 401),
        ]);

        $provider = $this->makeProvider(['credentials' => ['api_key' => 'sk-bad']]);

        $response = $this->auth()->postJson("/api/v1/ai-providers/{$provider->id}/test");

        $response->assertStatus(502)->assertJsonPath('data.ok', false);
        $this->assertNotNull($provider->fresh()->last_test_error);
    }

    // --- Gateway: failover, spend limits, usage logging (Http::fake) ---

    public function test_gateway_fails_over_to_next_provider_on_error()
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'down'], 500),
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'fallback OK']]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
                'model' => 'gpt-4o',
            ], 200),
        ]);

        $this->makeProvider(['provider' => 'anthropic', 'model' => 'claude-sonnet-5', 'is_active' => true, 'priority' => 1, 'credentials' => ['api_key' => 'x']]);
        $this->makeProvider(['provider' => 'openai', 'model' => 'gpt-4o', 'is_active' => true, 'priority' => 2, 'credentials' => ['api_key' => 'y']]);

        $gateway = app(AiGateway::class);
        $result = $gateway->complete($this->tenant->id, 'test_feature', [['role' => 'user', 'content' => 'hi']]);

        $this->assertEquals('fallback OK', $result->text);
        $this->assertEquals(2, AiUsageLog::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals('error', AiUsageLog::where('feature', 'test_feature')->where('status', 'error')->first()->status);
        $this->assertEquals('success', AiUsageLog::where('feature', 'test_feature')->where('status', 'success')->first()->status);
    }

    public function test_gateway_throws_when_no_provider_is_configured()
    {
        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('No active AI provider is configured');

        app(AiGateway::class)->complete($this->tenant->id, 'test_feature', [['role' => 'user', 'content' => 'hi']]);
    }

    public function test_gateway_skips_provider_over_its_spend_limit()
    {
        $provider = $this->makeProvider([
            'is_active' => true,
            'credentials' => ['api_key' => 'x'],
            'monthly_cost_limit_usd' => 1.00,
        ]);
        AiUsageLog::create([
            'tenant_id' => $this->tenant->id,
            'ai_provider_id' => $provider->id,
            'feature' => 'prior',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cost_usd' => 2.00,
            'status' => 'success',
            'created_at' => now(),
        ]);

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('monthly cost limit reached');

        app(AiGateway::class)->complete($this->tenant->id, 'test_feature', [['role' => 'user', 'content' => 'hi']]);
    }

    public function test_gateway_computes_real_cost_from_configured_rates()
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'hi']],
                'usage' => ['input_tokens' => 1_000_000, 'output_tokens' => 1_000_000],
                'model' => 'claude-sonnet-5',
            ], 200),
        ]);

        $this->makeProvider([
            'is_active' => true,
            'credentials' => ['api_key' => 'x'],
            'input_cost_per_million' => 3,
            'output_cost_per_million' => 15,
        ]);

        app(AiGateway::class)->complete($this->tenant->id, 'cost_feature', [['role' => 'user', 'content' => 'hi']]);

        $log = AiUsageLog::where('feature', 'cost_feature')->first();
        $this->assertEquals(18.0, (float) $log->cost_usd);
    }

    public function test_gateway_records_zero_cost_when_rates_not_configured_and_usage_flags_it()
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'hi']],
                'usage' => ['input_tokens' => 1000, 'output_tokens' => 500],
                'model' => 'claude-sonnet-5',
            ], 200),
        ]);

        $provider = $this->makeProvider(['is_active' => true, 'credentials' => ['api_key' => 'x']]);

        app(AiGateway::class)->complete($this->tenant->id, 'no_rate_feature', [['role' => 'user', 'content' => 'hi']]);

        $log = AiUsageLog::where('feature', 'no_rate_feature')->first();
        $this->assertEquals(0.0, (float) $log->cost_usd);

        $usage = $this->auth()->getJson('/api/v1/ai-usage');
        $usage->assertOk()->assertJsonPath('data.providers_without_cost_rates.0', $provider->id);
    }

    // --- Usage endpoint ---

    public function test_usage_endpoint_aggregates_real_logged_data()
    {
        $provider = $this->makeProvider(['is_active' => true]);
        AiUsageLog::create([
            'tenant_id' => $this->tenant->id,
            'ai_provider_id' => $provider->id,
            'feature' => 'sales_agent',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'cost_usd' => 0.50,
            'status' => 'success',
            'created_at' => now(),
        ]);
        AiUsageLog::create([
            'tenant_id' => $this->otherTenant->id,
            'ai_provider_id' => $provider->id,
            'feature' => 'sales_agent',
            'prompt_tokens' => 999,
            'completion_tokens' => 999,
            'cost_usd' => 99.00,
            'status' => 'success',
            'created_at' => now(),
        ]);

        $response = $this->auth()->getJson('/api/v1/ai-usage');

        $response->assertOk()
            ->assertJsonPath('data.total_calls', 1)
            ->assertJsonPath('data.total_cost_usd', 0.5);
    }
}
