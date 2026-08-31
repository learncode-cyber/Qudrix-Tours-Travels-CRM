<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\OfflineSync;
use App\Models\SyncQueue;
use App\Models\CachePolicy;
use App\Models\PWASettings;
use App\Models\OfflineData;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Phase8Test extends TestCase
{
    use RefreshDatabase;
    private $token;
    private $tenant;
    private $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'PWA Agency', 'slug' => 'pwa-agency']);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'PWA User',
            'email' => 'pwa@agency.com',
            'password' => bcrypt('password'),
        ]);
        $this->token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($this->user);
    }

    public function test_sync_offline_changes()
    {
        $changes = [
            ['entity_type' => 'booking', 'entity_id' => 1, 'operation' => 'update', 'payload' => ['status' => 'confirmed']],
            ['entity_type' => 'customer', 'entity_id' => 5, 'operation' => 'create', 'payload' => ['name' => 'Ahmed']]
        ];

        $response = $this->postJson('/api/v1/sync', ['changes' => $changes, 'batch_id' => 'batch_123'], 
            ['Authorization' => "Bearer $this->token"]);
        
        $this->assertEquals(200, $response->status());
        $this->assertDatabaseHas('offline_syncs', ['entity_type' => 'booking']);
    }

    public function test_get_pending_sync()
    {
        OfflineSync::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'entity_type' => 'booking',
            'entity_id' => 1,
            'operation' => 'update',
            'payload' => ['status' => 'pending'],
            'status' => 'pending'
        ]);

        $response = $this->getJson('/api/v1/sync/pending', ['Authorization' => "Bearer $this->token"]);
        
        $this->assertEquals(200, $response->status());
    }

    public function test_get_sync_status()
    {
        $response = $this->getJson('/api/v1/sync/status/batch_123', ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }

    public function test_retry_failed_sync()
    {
        $response = $this->postJson('/api/v1/sync/retry-failed', [], 
            ['Authorization' => "Bearer $this->token"]);
        
        $this->assertEquals(200, $response->status());
    }

    public function test_get_cache_policies()
    {
        CachePolicy::create([
            'tenant_id' => $this->tenant->id,
            'resource_type' => 'api',
            'cache_strategy' => 'network_first',
            'ttl_minutes' => 60,
            'max_size_mb' => 50,
            'is_active' => true
        ]);

        $response = $this->getJson('/api/v1/cache/policies', ['Authorization' => "Bearer $this->token"]);
        
        $this->assertEquals(200, $response->status());
    }

    public function test_create_cache_policy()
    {
        $response = $this->postJson('/api/v1/cache/policies', [
            'resource_type' => 'images',
            'cache_strategy' => 'cache_first',
            'ttl_minutes' => 1440,
            'max_size_mb' => 100,
            'priority' => 'high'
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(201, $response->status());
        $this->assertDatabaseHas('cache_policies', ['resource_type' => 'images']);
    }

    public function test_clear_cache()
    {
        $response = $this->postJson('/api/v1/cache/clear', [], 
            ['Authorization' => "Bearer $this->token"]);
        
        $this->assertEquals(200, $response->status());
    }

    public function test_get_cache_stats()
    {
        $response = $this->getJson('/api/v1/cache/stats', ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }

    public function test_get_pwa_manifest()
    {
        $response = $this->getJson('/api/v1/pwa/manifest.json', ['Authorization' => "Bearer $this->token"]);
        
        $this->assertEquals(200, $response->status());
        // The manifest is served flat, not wrapped in {"data": ...}: the
        // Web App Manifest spec requires top-level keys like "name" so
        // browsers can read it directly as manifest.json.
        $this->assertArrayHasKey('name', $response->json());
    }

    public function test_update_pwa_settings()
    {
        $response = $this->putJson('/api/v1/pwa/settings', [
            'app_name' => 'QUDRIX Pro',
            'theme_color' => '#ff5722',
            'offline_enabled' => true
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(200, $response->status());
    }

    public function test_get_service_worker()
    {
        $response = $this->get('/api/v1/sw.js', ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }

    public function test_download_offline_data()
    {
        $response = $this->getJson('/api/v1/offline/data?type=bookings', 
            ['Authorization' => "Bearer $this->token"]);
        
        $this->assertEquals(200, $response->status());
    }

    public function test_get_offline_status()
    {
        OfflineData::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'data_type' => 'bookings',
            'data' => ['booking_id' => 1],
            'size_kb' => 10
        ]);

        $response = $this->getJson('/api/v1/offline/status', ['Authorization' => "Bearer $this->token"]);
        
        $this->assertEquals(200, $response->status());
    }

    public function test_sync_offline_changes_endpoint()
    {
        $changes = [
            ['type' => 'bookings', 'data' => ['id' => 1, 'status' => 'confirmed']]
        ];

        $response = $this->postJson('/api/v1/offline/sync', ['changes' => $changes], 
            ['Authorization' => "Bearer $this->token"]);
        
        $this->assertEquals(200, $response->status());
    }

    public function test_clear_offline_data()
    {
        $response = $this->postJson('/api/v1/offline/clear', [], 
            ['Authorization' => "Bearer $this->token"]);
        
        $this->assertEquals(200, $response->status());
    }
}
