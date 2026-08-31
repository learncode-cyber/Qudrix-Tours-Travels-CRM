<?php

namespace Tests\Feature;

use App\Models\ApiConnector;
use App\Models\ApiConnectorEndpoint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase8ApiConnectorTest extends TestCase
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
            'name' => 'Connector Tenant',
            'slug' => 'connector-tenant',
            'is_active' => true,
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-p8',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Connector User',
            'email' => 'connector@example.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->token = JWTAuth::fromUser($this->user);

        config(['integrations.allow_private_network_connectors' => true]);
    }

    private function auth()
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    private function makeConnector(array $overrides = []): ApiConnector
    {
        return ApiConnector::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mock Flight Provider',
            'slug' => 'mock-flight-provider-' . uniqid(),
            'category' => 'flight',
            'base_url' => 'https://mock-provider.test',
            'auth_type' => 'bearer',
            'status' => 'unconfigured',
            'is_active' => false,
        ], $overrides));
    }

    // --- CRUD + credential hiding ---

    public function test_connector_create_hides_credentials_from_response()
    {
        $response = $this->auth()->postJson('/api/v1/api-connectors', [
            'name' => 'Test Provider',
            'category' => 'flight',
            'base_url' => 'https://provider.test',
            'auth_type' => 'bearer',
            'credentials' => ['token' => 'super-secret'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('contract_required', true)
            ->assertJsonMissingPath('data.credentials');
        $this->assertStringNotContainsString('super-secret', $response->getContent());
    }

    public function test_credentials_endpoint_never_returns_them()
    {
        $connector = $this->makeConnector();

        $response = $this->auth()->putJson("/api/v1/api-connectors/{$connector->id}/credentials", [
            'credentials' => ['token' => 'another-secret'],
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('another-secret', $response->getContent());

        $show = $this->auth()->getJson("/api/v1/api-connectors/{$connector->id}");
        $this->assertStringNotContainsString('another-secret', $show->getContent());
    }

    public function test_connectors_are_tenant_scoped()
    {
        $mine = $this->makeConnector(['name' => 'Mine']);
        $this->makeConnector(['tenant_id' => $this->otherTenant->id, 'name' => 'Not Mine', 'slug' => 'not-mine-' . uniqid()]);

        $response = $this->auth()->getJson('/api/v1/api-connectors');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($mine->id, $response->json('data.0.id'));
    }

    // --- Contract-required activation guard ---

    public function test_activation_is_refused_without_a_mapped_endpoint()
    {
        $connector = $this->makeConnector();

        $response = $this->auth()->putJson("/api/v1/api-connectors/{$connector->id}", ['is_active' => true]);

        $response->assertStatus(422)->assertJsonPath(
            'error',
            'CONTRACT REQUIRED: map at least one active endpoint before activating this connector.'
        );
        $this->assertFalse($connector->fresh()->is_active);
    }

    public function test_activation_succeeds_once_an_endpoint_is_mapped()
    {
        $connector = $this->makeConnector();
        ApiConnectorEndpoint::create([
            'api_connector_id' => $connector->id,
            'operation' => 'search',
            'http_method' => 'GET',
            'path' => '/search',
            'is_active' => true,
        ]);

        $response = $this->auth()->putJson("/api/v1/api-connectors/{$connector->id}", ['is_active' => true]);

        $response->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_endpoint_mapping_crud()
    {
        $connector = $this->makeConnector();

        $create = $this->auth()->postJson("/api/v1/api-connectors/{$connector->id}/endpoints", [
            'operation' => 'search',
            'http_method' => 'GET',
            'path' => '/flights/search',
            'query_template' => ['origin' => '{{origin}}'],
            'response_mapping' => ['price' => 'fare.total'],
            'response_collection_path' => 'results',
        ]);
        $create->assertStatus(201)->assertJsonPath('data.operation', 'search');
        $endpointId = $create->json('data.id');

        $show = $this->auth()->getJson("/api/v1/api-connectors/{$connector->id}");
        $show->assertOk()->assertJsonPath('contract_required', false);

        $this->auth()->deleteJson("/api/v1/api-connectors/{$connector->id}/endpoints/{$endpointId}")->assertOk();

        $showAfter = $this->auth()->getJson("/api/v1/api-connectors/{$connector->id}");
        $showAfter->assertOk()->assertJsonPath('contract_required', true);
    }

    // --- SSRF guard ---

    public function test_execute_blocks_private_network_targets_when_not_explicitly_allowed()
    {
        config(['integrations.allow_private_network_connectors' => false]);

        $connector = $this->makeConnector(['base_url' => 'http://127.0.0.1:9999', 'is_active' => true]);
        ApiConnectorEndpoint::create([
            'api_connector_id' => $connector->id,
            'operation' => 'search',
            'http_method' => 'GET',
            'path' => '/search',
            'is_active' => true,
        ]);

        $response = $this->auth()->postJson("/api/v1/api-connectors/{$connector->id}/execute", ['operation' => 'search']);

        $response->assertStatus(502)
            ->assertJsonPath('error', fn ($msg) => str_contains($msg, 'private or reserved address'));
    }

    // --- Execute pipeline: substitution, mapping, logging, honesty ---

    public function test_execute_rejects_unmapped_operation_with_contract_required()
    {
        $connector = $this->makeConnector(['is_active' => true]);
        ApiConnectorEndpoint::create([
            'api_connector_id' => $connector->id,
            'operation' => 'search',
            'http_method' => 'GET',
            'path' => '/search',
            'is_active' => true,
        ]);

        $response = $this->auth()->postJson("/api/v1/api-connectors/{$connector->id}/execute", ['operation' => 'book']);

        $response->assertStatus(502)
            ->assertJsonPath('error', fn ($msg) => str_contains($msg, 'CONTRACT REQUIRED') && str_contains($msg, "'book'"));
    }

    public function test_execute_rejects_when_connector_is_not_active()
    {
        $connector = $this->makeConnector(['is_active' => false]);

        $response = $this->auth()->postJson("/api/v1/api-connectors/{$connector->id}/execute", ['operation' => 'search']);

        $response->assertStatus(502)->assertJsonPath('error', fn ($msg) => str_contains($msg, 'is not active'));
    }

    public function test_execute_substitutes_credentials_and_maps_the_real_response()
    {
        Http::fake([
            'mock-provider.test/*' => Http::response([
                'results' => [
                    ['fare' => ['total' => 199.99], 'carrier' => 'MOCK'],
                    ['fare' => ['total' => 299.50], 'carrier' => 'MOCK2'],
                ],
            ], 200),
        ]);

        $connector = $this->makeConnector(['is_active' => true, 'credentials' => ['token' => 'real-secret-token']]);
        ApiConnectorEndpoint::create([
            'api_connector_id' => $connector->id,
            'operation' => 'search',
            'http_method' => 'GET',
            'path' => '/flights/search',
            'query_template' => ['origin' => '{{origin}}'],
            'response_mapping' => ['price' => 'fare.total', 'carrier' => 'carrier'],
            'response_collection_path' => 'results',
            'is_active' => true,
        ]);

        $response = $this->auth()->postJson("/api/v1/api-connectors/{$connector->id}/execute", [
            'operation' => 'search',
            'params' => ['origin' => 'DXB'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.mapped.0.price', 199.99)
            ->assertJsonPath('data.mapped.0.carrier', 'MOCK')
            ->assertJsonPath('data.mapped.1.price', 299.5);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer real-secret-token')
                && str_contains((string) $request->url(), 'origin=DXB');
        });

        $this->assertDatabaseHas('api_connector_call_logs', [
            'api_connector_id' => $connector->id,
            'operation' => 'search',
            'success' => 1,
        ]);
        $log = \App\Models\ApiConnectorCallLog::where('api_connector_id', $connector->id)->first();
        $this->assertStringNotContainsString('real-secret-token', json_encode($log->request_payload));
    }

    public function test_execute_reports_provider_failure_honestly_and_logs_it()
    {
        Http::fake([
            'mock-provider.test/*' => Http::response(['error' => 'bad request'], 400),
        ]);

        $connector = $this->makeConnector(['is_active' => true]);
        ApiConnectorEndpoint::create([
            'api_connector_id' => $connector->id,
            'operation' => 'search',
            'http_method' => 'GET',
            'path' => '/search',
            'is_active' => true,
        ]);

        $response = $this->auth()->postJson("/api/v1/api-connectors/{$connector->id}/execute", ['operation' => 'search']);

        $response->assertStatus(502)->assertJsonPath('error', 'Provider returned HTTP 400');
        $this->assertDatabaseHas('api_connector_call_logs', [
            'api_connector_id' => $connector->id,
            'success' => 0,
        ]);
    }

    public function test_test_connection_records_real_outcome_on_the_connector()
    {
        Http::fake([
            'mock-provider.test*' => Http::response(['ok' => true], 200),
        ]);

        $connector = $this->makeConnector();

        $response = $this->auth()->postJson("/api/v1/api-connectors/{$connector->id}/test-connection");

        $response->assertOk()->assertJsonPath('data.connected', true);
        $this->assertEquals('connected', $connector->fresh()->status);
    }

    public function test_call_logs_are_tenant_scoped_via_connector_ownership()
    {
        $connector = $this->makeConnector();
        $otherConnector = $this->makeConnector(['tenant_id' => $this->otherTenant->id, 'slug' => 'other-' . uniqid()]);

        $response = $this->auth()->getJson("/api/v1/api-connectors/{$otherConnector->id}/call-logs");

        $response->assertStatus(404);
    }
}
