<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Communication;
use App\Models\Customer;
use App\Models\Flight;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase10AiSalesAgentTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'AI Sales Tenant',
            'slug' => 'ai-sales-tenant',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'AI Sales User',
            'email' => 'aisales@example.com',
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
            'name' => 'AI Sales Lead',
            'email' => 'aisaleslead@example.com',
            'phone' => '+15557778888',
            'source' => 'website',
            'status' => 'new',
            'priority' => 'medium',
        ], $overrides));
    }

    // --- Honest failure paths (no provider / provider failure) ---

    public function test_qualify_lead_fails_honestly_with_no_active_provider()
    {
        $lead = $this->makeLead();

        $response = $this->auth()->postJson("/api/v1/ai/leads/{$lead->id}/qualify");

        $response->assertStatus(502)
            ->assertJsonPath('error', fn ($msg) => str_contains($msg, 'No active AI provider is configured'));
    }

    public function test_summarize_short_circuits_with_no_communications_without_calling_the_gateway()
    {
        Http::fake(); // any real HTTP call in this test fails the assertion below
        $lead = $this->makeLead();

        $response = $this->auth()->postJson("/api/v1/ai/leads/{$lead->id}/summarize");

        $response->assertOk()->assertJsonPath('data.message', 'This lead has no recorded communications to summarize.');
        Http::assertNothingSent();
    }

    public function test_qualify_lead_propagates_real_provider_failure_honestly()
    {
        $this->activeAnthropicProvider();
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'invalid key']], 401)]);
        $lead = $this->makeLead();

        $response = $this->auth()->postJson("/api/v1/ai/leads/{$lead->id}/qualify");

        $response->assertStatus(502)
            ->assertJsonPath('error', fn ($msg) => str_contains($msg, 'HTTP 401'));
    }

    // --- Qualify lead: grounding + suggestion persistence ---

    public function test_qualify_lead_persists_an_ai_suggested_score_a_human_can_see()
    {
        $this->activeAnthropicProvider();
        $this->fakeAnthropicJson([
            'score' => 72,
            'buying_intent' => 'high',
            'reasoning' => 'Multiple recent contacts, asked about pricing twice.',
            'signals' => [['signal' => 'asked about pricing', 'impact' => 'positive']],
            'recommended_next_action' => 'Send a proposal',
            'suggested_follow_up_days' => 2,
            'objections_detected' => [],
            'missing_information' => [],
        ]);
        $lead = $this->makeLead();

        $response = $this->auth()->postJson("/api/v1/ai/leads/{$lead->id}/qualify");

        $response->assertOk()
            ->assertJsonPath('data.score', 72)
            ->assertJsonPath('data.is_suggestion', true)
            ->assertJsonPath('data.human_can_override', true);

        $this->assertDatabaseHas('lead_scores', [
            'lead_id' => $lead->id,
            'score' => 72,
            'score_type' => 'ai_suggested',
        ]);
    }

    public function test_qualify_lead_sends_only_real_grounded_data_no_fabricated_context()
    {
        $this->activeAnthropicProvider();
        $this->fakeAnthropicJson(['score' => 50, 'buying_intent' => 'medium', 'reasoning' => 'x']);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Grounded Customer',
            'customer_type' => 'individual',
        ]);
        $lead = $this->makeLead(['customer_id' => $customer->id, 'name' => 'Real Lead Name']);
        Communication::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'type' => 'email',
            'subject' => 'Re: trip',
            'message' => 'Real message body the customer actually sent.',
            'status' => 'received',
        ]);

        $this->auth()->postJson("/api/v1/ai/leads/{$lead->id}/qualify")->assertOk();

        Http::assertSent(function ($request) {
            $body = $request->body();
            return str_contains($body, 'Real Lead Name')
                && str_contains($body, 'Real message body the customer actually sent.');
        });
    }

    // --- Suggest reply: draft-only, never sent ---

    public function test_suggest_reply_returns_a_draft_never_marked_as_sent()
    {
        $this->activeAnthropicProvider();
        $this->fakeAnthropicJson([
            'draft' => 'Thanks for reaching out! [CONFIRM PRICE]',
            'tone' => 'friendly',
            'rationale' => 'Acknowledges interest, flags the unverified price.',
            'facts_to_verify_before_sending' => ['final price'],
        ]);
        $lead = $this->makeLead();

        $response = $this->auth()->postJson("/api/v1/ai/leads/{$lead->id}/suggest-reply", [
            'rep_intent' => 'confirm interest and ask about dates',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_draft', true)
            ->assertJsonPath('data.sent', false)
            ->assertJsonPath('data.draft', 'Thanks for reaching out! [CONFIRM PRICE]');
    }

    // --- AI Package Builder: interpret + propose with real verification ---

    public function test_interpret_extracts_structured_requirements()
    {
        $this->activeAnthropicProvider();
        $this->fakeAnthropicJson([
            'destination' => 'Dubai',
            'travel_date' => '2027-06-01',
            'return_date' => null,
            'group_size' => 2,
            'budget_amount' => null,
            'budget_currency' => null,
            'needs' => ['flight' => true, 'hotel' => true, 'transport' => false],
            'notes' => null,
            'missing_information' => ['return date', 'budget'],
        ]);

        $response = $this->auth()->postJson('/api/v1/ai/package-builder/interpret', [
            'text' => 'We want to go to Dubai on June 1st, 2 people.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.destination', 'Dubai')
            ->assertJsonPath('data.group_size', 2);
    }

    public function test_propose_reports_no_inventory_without_calling_the_gateway()
    {
        Http::fake();
        $this->activeAnthropicProvider();

        $response = $this->auth()->postJson('/api/v1/ai/package-builder/propose', [
            'requirements' => ['destination' => 'Nowhereland', 'group_size' => 2],
        ]);

        $response->assertOk()->assertJsonPath('data.proposal', null);
        Http::assertNothingSent();
    }

    public function test_propose_verifies_ai_named_components_against_real_inventory_and_prices_deterministically()
    {
        $this->activeAnthropicProvider();
        $flight = Flight::create([
            'tenant_id' => $this->tenant->id,
            'airline_code' => 'EK',
            'flight_number' => 'EK202',
            'departure_airport' => 'DXB',
            'arrival_airport' => 'JFK',
            'departure_date' => now()->addMonths(3),
            'arrival_date' => now()->addMonths(3),
            'departure_time' => '08:00:00',
            'arrival_time' => '14:00:00',
            'aircraft_type' => 'A380',
            'total_seats' => 200,
            'available_seats' => 200,
            'price_per_seat' => 900,
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $this->fakeAnthropicJson([
            'components' => [
                ['type' => 'flight', 'reference_id' => $flight->id, 'quantity' => 2, 'why' => 'matches requested route'],
            ],
            'alternatives' => [],
            'upsell_suggestions' => [],
            'summary' => 'One real flight option matches.',
        ]);

        $response = $this->auth()->postJson('/api/v1/ai/package-builder/propose', [
            'requirements' => ['destination' => 'Dubai', 'group_size' => 2, 'travel_date' => now()->addMonths(3)->toDateString()],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.requires_human_approval', true)
            ->assertJsonCount(1, 'data.verified');
        $this->assertEquals(1800.0, (float) $response->json('data.pricing.base_cost'));
    }

    public function test_propose_rejects_a_hallucinated_component_id()
    {
        $this->activeAnthropicProvider();
        Flight::create([
            'tenant_id' => $this->tenant->id,
            'airline_code' => 'EK',
            'flight_number' => 'EK202',
            'departure_airport' => 'DXB',
            'arrival_airport' => 'JFK',
            'departure_date' => now()->addMonths(3),
            'arrival_date' => now()->addMonths(3),
            'departure_time' => '08:00:00',
            'arrival_time' => '14:00:00',
            'aircraft_type' => 'A380',
            'total_seats' => 200,
            'available_seats' => 200,
            'price_per_seat' => 900,
            'currency' => 'USD',
            'status' => 'active',
        ]);

        // The model "hallucinates" a reference_id that does not exist.
        $this->fakeAnthropicJson([
            'components' => [
                ['type' => 'flight', 'reference_id' => 999999, 'quantity' => 1, 'why' => 'hallucinated'],
            ],
            'alternatives' => [],
            'upsell_suggestions' => [],
            'summary' => 'x',
        ]);

        $response = $this->auth()->postJson('/api/v1/ai/package-builder/propose', [
            'requirements' => ['destination' => 'Dubai', 'group_size' => 1],
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'The proposed package failed inventory verification.');
    }
}
