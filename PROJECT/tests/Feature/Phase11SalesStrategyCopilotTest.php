<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Communication;
use App\Models\Customer;
use App\Models\CustomerMemory;
use App\Models\Lead;
use App\Models\SalesStrategy;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase11SalesStrategyCopilotTest extends TestCase
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
            'name' => 'Copilot Tenant',
            'slug' => 'copilot-tenant',
            'is_active' => true,
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-p11',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Copilot User',
            'email' => 'copilot@example.com',
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

    private function activeAnthropicProvider(): AiProvider
    {
        return AiProvider::create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-5',
            'is_active' => true,
            'is_default' => true,
            'credentials' => ['api_key' => 'sk-test'],
        ]);
    }

    private function fakeAnthropicJson(array $payload): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode($payload)]],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 50],
                'model' => 'claude-sonnet-5',
            ], 200),
        ]);
    }

    private function makeLead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Copilot Lead',
            'email' => 'copilotlead@example.com',
            'phone' => '+15556667777',
            'source' => 'website',
            'status' => 'new',
            'priority' => 'high',
        ], $overrides));
    }

    // --- Sales Strategies CRUD ---

    public function test_sales_strategy_crud_lifecycle_and_key_validation()
    {
        $create = $this->auth()->postJson('/api/v1/sales-strategies', [
            'key' => 'consultative',
            'name' => 'Consultative Selling',
            'prompt_guidance' => 'Ask open questions first.',
            'tone' => 'warm',
            'priority' => 1,
        ]);
        $create->assertStatus(201)->assertJsonPath('data.is_active', true);
        $id = $create->json('data.id');

        $this->auth()->getJson('/api/v1/sales-strategies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('available_keys.0', 'consultative');

        $this->auth()->putJson("/api/v1/sales-strategies/{$id}", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->auth()->deleteJson("/api/v1/sales-strategies/{$id}")->assertOk();
        $this->auth()->getJson('/api/v1/sales-strategies')->assertOk()->assertJsonCount(0, 'data');

        $invalid = $this->auth()->postJson('/api/v1/sales-strategies', [
            'key' => 'made_up_methodology',
            'name' => 'x',
            'prompt_guidance' => 'x',
        ]);
        $invalid->assertStatus(422);
    }

    public function test_sales_strategies_are_tenant_scoped()
    {
        $mine = SalesStrategy::create([
            'tenant_id' => $this->tenant->id,
            'key' => 'consultative',
            'name' => 'Mine',
            'prompt_guidance' => 'x',
            'is_active' => true,
        ]);
        SalesStrategy::create([
            'tenant_id' => $this->otherTenant->id,
            'key' => 'spin',
            'name' => 'Not Mine',
            'prompt_guidance' => 'x',
            'is_active' => true,
        ]);

        $response = $this->auth()->getJson('/api/v1/sales-strategies');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($mine->id, $response->json('data.0.id'));
    }

    // --- Customer Memory CRUD ---

    public function test_memory_index_requires_customer_or_lead_id()
    {
        $this->auth()->getJson('/api/v1/customer-memories')
            ->assertStatus(422)->assertJsonPath('error', 'Provide customer_id or lead_id.');
    }

    public function test_memory_crud_lifecycle_and_category_validation()
    {
        $lead = $this->makeLead();

        $create = $this->auth()->postJson('/api/v1/customer-memories', [
            'lead_id' => $lead->id,
            'category' => 'budget',
            'key' => 'budget_amount',
            'value' => '5000 USD',
        ]);
        $create->assertStatus(201)->assertJsonPath('data.source', 'human');
        $id = $create->json('data.id');

        $this->auth()->getJson("/api/v1/customer-memories?lead_id={$lead->id}")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->auth()->putJson("/api/v1/customer-memories/{$id}", ['value' => '6000 USD'])
            ->assertOk()->assertJsonPath('data.value', '6000 USD');

        $this->auth()->deleteJson("/api/v1/customer-memories/{$id}")->assertOk();
        $this->auth()->getJson("/api/v1/customer-memories?lead_id={$lead->id}")
            ->assertOk()->assertJsonCount(0, 'data');

        $invalid = $this->auth()->postJson('/api/v1/customer-memories', [
            'lead_id' => $lead->id,
            'category' => 'made_up_category',
            'key' => 'x',
            'value' => 'x',
        ]);
        $invalid->assertStatus(422);
    }

    public function test_memory_requires_customer_or_lead_on_create()
    {
        $response = $this->auth()->postJson('/api/v1/customer-memories', [
            'category' => 'budget',
            'key' => 'x',
            'value' => 'x',
        ]);
        $response->assertStatus(422)->assertJsonPath('error', 'A memory entry must be linked to a customer or a lead.');
    }

    // --- Regression: lead update must accept customer_id ---

    public function test_lead_update_accepts_customer_id_link()
    {
        $lead = $this->makeLead();
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Linked Customer',
            'customer_type' => 'individual',
        ]);

        $response = $this->auth()->putJson("/api/v1/leads/{$lead->id}", ['customer_id' => $customer->id]);

        $response->assertOk()->assertJsonPath('data.customer_id', $customer->id);
        $this->assertEquals($customer->id, $lead->fresh()->customer_id);
    }

    // --- AI Copilot: honest failure + grounding ---

    public function test_copilot_assist_fails_honestly_with_no_active_provider()
    {
        $lead = $this->makeLead();

        $response = $this->auth()->postJson("/api/v1/ai/leads/{$lead->id}/copilot", [
            'latest_customer_message' => 'Any deals for June?',
        ]);

        $response->assertStatus(502)
            ->assertJsonPath('error', fn ($msg) => str_contains($msg, 'No active AI provider is configured'));
    }

    public function test_extract_memory_short_circuits_with_no_messages_without_calling_the_gateway()
    {
        Http::fake();
        $this->activeAnthropicProvider();
        $lead = $this->makeLead();

        $response = $this->auth()->postJson("/api/v1/ai/leads/{$lead->id}/extract-memory");

        $response->assertOk()->assertJsonPath('data.candidates', []);
        Http::assertNothingSent();
    }

    public function test_copilot_uses_the_active_strategy_and_returns_suggestion_only_flags()
    {
        $this->activeAnthropicProvider();
        SalesStrategy::create([
            'tenant_id' => $this->tenant->id,
            'key' => 'consultative',
            'name' => 'Consultative Selling',
            'prompt_guidance' => 'Ask open questions.',
            'tone' => 'warm',
            'priority' => 1,
            'is_active' => true,
        ]);
        $this->fakeAnthropicJson([
            'suggested_next_question' => 'What dates work best for you?',
            'objection_handling' => [],
            'recommended_products' => ['family package'],
            'upsell_opportunities' => [],
            'suggested_follow_up_timing' => 'in 2 days',
            'customer_sentiment' => 'positive',
            'context_notes' => 'x',
            'facts_to_verify' => [],
        ]);
        $lead = $this->makeLead();

        $response = $this->auth()->postJson("/api/v1/ai/leads/{$lead->id}/copilot", [
            'latest_customer_message' => 'Any deals for June?',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.strategy_used', 'consultative')
            ->assertJsonPath('data.is_suggestion', true)
            ->assertJsonPath('data.human_in_control', true);
    }

    public function test_copilot_never_sends_sensitive_memory_to_the_model()
    {
        $this->activeAnthropicProvider();
        $this->fakeAnthropicJson(['suggested_next_question' => 'x', 'customer_sentiment' => 'neutral']);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Memory Customer',
            'customer_type' => 'individual',
        ]);
        $lead = $this->makeLead(['customer_id' => $customer->id]);

        CustomerMemory::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'category' => 'budget',
            'key' => 'budget_amount',
            'value' => 'NON_SENSITIVE_BUDGET_VALUE',
            'is_sensitive' => false,
            'created_by' => $this->user->id,
        ]);
        CustomerMemory::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'category' => 'requirement',
            'key' => 'medical_condition',
            'value' => 'SECRET_MEDICAL_DETAIL',
            'is_sensitive' => true,
            'created_by' => $this->user->id,
        ]);

        $this->auth()->postJson("/api/v1/ai/leads/{$lead->id}/copilot")->assertOk();

        Http::assertSent(function ($request) {
            $body = $request->body();
            return str_contains($body, 'NON_SENSITIVE_BUDGET_VALUE')
                && !str_contains($body, 'SECRET_MEDICAL_DETAIL');
        });
    }

    public function test_extract_memory_returns_candidates_requiring_human_confirmation_never_auto_stored()
    {
        $this->activeAnthropicProvider();
        $this->fakeAnthropicJson([
            'candidates' => [
                [
                    'category' => 'destination',
                    'key' => 'preferred_destination',
                    'value' => 'Bali',
                    'confidence' => 0.9,
                    'evidence' => 'We want to visit Bali in July.',
                    'possibly_sensitive' => false,
                ],
            ],
        ]);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Extract Customer',
            'customer_type' => 'individual',
        ]);
        $lead = $this->makeLead(['customer_id' => $customer->id]);
        Communication::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'type' => 'email',
            'subject' => 'Trip',
            'message' => 'We want to visit Bali in July.',
            'status' => 'received',
        ]);

        $response = $this->auth()->postJson("/api/v1/ai/leads/{$lead->id}/extract-memory");

        $response->assertOk()
            ->assertJsonPath('data.requires_human_confirmation', true)
            ->assertJsonPath('data.stored', false)
            ->assertJsonCount(1, 'data.candidates');
        $this->assertEquals(0, CustomerMemory::where('lead_id', $lead->id)->count());
    }
}
