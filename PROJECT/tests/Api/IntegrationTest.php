<?php

namespace Tests\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\WebsiteIntegration;
use App\Services\IntegrationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;

class IntegrationTest extends TestCase
{
    protected $user;
    protected $tenant;
    protected $integrationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()
            ->for($this->tenant)
            ->create(['role' => 'admin']);

        $this->integrationService = app(IntegrationService::class);
    }

    /**
     * Test: Create integration
     */
    public function test_create_integration()
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/admin/api/integrations', [
                'name' => 'Test Website',
                'website_url' => 'https://example.com',
                'crm_base_url' => 'https://crm.example.com/api/v1',
                'description' => 'Test integration',
                'sync_settings' => [
                    'auto_sync' => true,
                    'sync_interval_minutes' => 15,
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Integration created successfully',
            ])
            ->assertJsonStructure([
                'data' => ['id', 'name', 'status', 'webhook_secret', 'webhook_url'],
            ]);

        $this->assertDatabaseHas('website_integrations', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Website',
            'status' => 'pending',
        ]);
    }

    /**
     * Test: List integrations
     */
    public function test_list_integrations()
    {
        WebsiteIntegration::factory(3)
            ->for($this->tenant)
            ->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/admin/api/integrations');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 3,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'website_url', 'status', 'is_active'],
                ],
            ]);
    }

    /**
     * Test: Get integration details
     */
    public function test_show_integration()
    {
        $integration = WebsiteIntegration::factory()
            ->for($this->tenant)
            ->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/admin/api/integrations/{$integration->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $integration->id,
                    'name' => $integration->name,
                ],
            ]);
    }

    /**
     * Test: Update integration
     */
    public function test_update_integration()
    {
        $integration = WebsiteIntegration::factory()
            ->for($this->tenant)
            ->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/admin/api/integrations/{$integration->id}", [
                'name' => 'New Name',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('website_integrations', [
            'id' => $integration->id,
            'name' => 'New Name',
        ]);
    }

    /**
     * Test: Save credentials securely
     */
    public function test_save_credentials()
    {
        $integration = WebsiteIntegration::factory()
            ->for($this->tenant)
            ->create();

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/admin/api/integrations/{$integration->id}/credentials", [
                'api_key' => 'qd_test_api_key',
                'api_secret' => 'sk_test_api_secret',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $integration->refresh();

        // Verify credentials are encrypted
        $this->assertNotEmpty($integration->crm_api_key);
        $this->assertNotEmpty($integration->crm_api_secret);
        $this->assertNotEqual('qd_test_api_key', $integration->crm_api_key);
        $this->assertNotEqual('sk_test_api_secret', $integration->crm_api_secret);

        // Verify decrypted values match
        $this->assertEquals('qd_test_api_key', $integration->getDecryptedApiKey());
        $this->assertEquals('sk_test_api_secret', $integration->getDecryptedApiSecret());
    }

    /**
     * Test: Test connection to CRM
     */
    public function test_connection_to_crm()
    {
        $integration = WebsiteIntegration::factory()
            ->for($this->tenant)
            ->create();

        $this->integrationService->updateCredentials(
            $integration,
            'qd_test_key',
            'sk_test_secret'
        );

        Http::fake([
            '*/api/v1/health' => Http::response([
                'success' => true,
                'api_version' => 'v1',
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/admin/api/integrations/{$integration->id}/test-connection");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Connection test successful',
            ]);

        $integration->refresh();
        $this->assertEquals('connected', $integration->status);
        $this->assertEquals('success', $integration->last_connection_status);
    }

    /**
     * Test: Audit logging
     */
    public function test_audit_logging()
    {
        $integration = WebsiteIntegration::factory()
            ->for($this->tenant)
            ->create();

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/admin/api/integrations/{$integration->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/admin/api/integrations/{$integration->id}/audit-logs");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'action', 'user_id', 'old_values', 'new_values', 'created_at'],
                ],
            ]);

        $this->assertDatabaseHas('integration_audit_logs', [
            'website_integration_id' => $integration->id,
            'action' => 'update',
        ]);
    }

    /**
     * Test: Validate credentials required for connection test
     */
    public function test_connection_test_requires_credentials()
    {
        $integration = WebsiteIntegration::factory()
            ->for($this->tenant)
            ->create(['crm_api_key' => null, 'crm_api_secret' => null]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/admin/api/integrations/{$integration->id}/test-connection");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Credentials not configured',
            ]);
    }

    /**
     * Test: Delete integration
     */
    public function test_delete_integration()
    {
        $integration = WebsiteIntegration::factory()
            ->for($this->tenant)
            ->create();

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/admin/api/integrations/{$integration->id}", [
                'reason' => 'Testing deletion',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Integration deleted successfully',
            ]);

        $this->assertSoftDeleted('website_integrations', [
            'id' => $integration->id,
        ]);

        $this->assertDatabaseHas('integration_audit_logs', [
            'website_integration_id' => $integration->id,
            'action' => 'delete',
        ]);
    }

    /**
     * Test: Authorization - non-admin cannot manage integrations
     */
    public function test_authorization_required()
    {
        $regularUser = User::factory()
            ->for($this->tenant)
            ->create(['role' => 'user']);

        $response = $this->actingAs($regularUser, 'api')
            ->getJson('/admin/api/integrations');

        $response->assertStatus(403);
    }

    /**
     * Test: Tenant isolation - cannot access other tenant's integrations
     */
    public function test_tenant_isolation()
    {
        $otherTenant = Tenant::factory()->create();
        $otherIntegration = WebsiteIntegration::factory()
            ->for($otherTenant)
            ->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/admin/api/integrations/{$otherIntegration->id}");

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    /**
     * Test: Get integration statistics
     */
    public function test_integration_statistics()
    {
        $integration = WebsiteIntegration::factory()
            ->for($this->tenant)
            ->create();

        // Create sync logs
        $integration->syncLogs()->create([
            'sync_type' => 'manual',
            'entity_type' => 'leads',
            'entity_count' => 5,
            'status' => 'success',
            'started_at' => now(),
            'completed_at' => now()->addSeconds(2),
            'duration_ms' => 2000,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/admin/api/integrations/{$integration->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.statistics.total_syncs_30d', 1)
            ->assertJsonPath('data.statistics.successful_syncs', 1)
            ->assertJsonPath('data.statistics.success_rate', 100)
            ->assertJsonPath('data.statistics.total_entities_synced', 5);
    }

    /**
     * Test: Validation - website URL must be valid
     */
    public function test_validation_website_url()
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/admin/api/integrations', [
                'name' => 'Test',
                'website_url' => 'not-a-url',
                'crm_base_url' => 'https://crm.example.com/api/v1',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('website_url');
    }
}
