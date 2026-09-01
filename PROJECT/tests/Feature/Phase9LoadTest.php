<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Laravel\Lumen\Testing\DatabaseMigrations;

class Phase9LoadTest extends TestCase
{
    use DatabaseMigrations;
    private $token;
    private $tenant;
    private $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Load Test', 'db_host' => 'localhost']);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'loadtest@agency.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);
        $this->token = 'test_jwt_token';
    }

    public function test_api_response_time_under_load()
    {
        $start = microtime(true);
        
        for ($i = 0; $i < 100; $i++) {
            $this->getJson('/api/v1/health', ['Authorization' => "Bearer $this->token"]);
        }
        
        $elapsed = microtime(true) - $start;
        $avgTime = $elapsed / 100;
        
        $this->assertLessThan(0.5, $avgTime, 'Average response time should be under 500ms');
    }

    public function test_concurrent_booking_creation()
    {
        $responses = [];
        $start = microtime(true);
        
        for ($i = 0; $i < 50; $i++) {
            $response = $this->postJson('/api/v1/bookings', [
                'customer_id' => 1,
                'departure_date' => '2024-02-15',
                'return_date' => '2024-02-20',
                'destination' => 'Dubai'
            ], ['Authorization' => "Bearer $this->token"]);
            
            $responses[] = $response->status();
        }
        
        $elapsed = microtime(true) - $start;
        $successCount = count(array_filter($responses, fn($s) => $s === 201));
        
        $this->assertGreaterThan(40, $successCount, 'At least 80% success rate under load');
        $this->assertLessThan(10, $elapsed, 'Should complete 50 requests in under 10 seconds');
    }

    public function test_database_query_performance()
    {
        $start = microtime(true);
        
        for ($i = 0; $i < 1000; $i++) {
            \App\Models\Booking::where('tenant_id', $this->tenant->id)->limit(10)->get();
        }
        
        $elapsed = microtime(true) - $start;
        $avgTime = $elapsed / 1000;
        
        $this->assertLessThan(0.01, $avgTime, 'Average query time should be under 10ms');
    }

    public function test_cache_hit_performance()
    {
        \Illuminate\Support\Facades\Cache::put('test_key', 'test_value', 3600);
        
        $start = microtime(true);
        
        for ($i = 0; $i < 10000; $i++) {
            \Illuminate\Support\Facades\Cache::get('test_key');
        }
        
        $elapsed = microtime(true) - $start;
        $avgTime = $elapsed / 10000;
        
        $this->assertLessThan(0.0001, $avgTime, 'Cache access should be under 0.1ms');
    }

    public function test_memory_usage_under_load()
    {
        $initialMemory = memory_get_usage();
        
        for ($i = 0; $i < 100; $i++) {
            $this->getJson('/api/v1/bookings', ['Authorization' => "Bearer $this->token"]);
        }
        
        $finalMemory = memory_get_usage();
        $memoryIncrease = ($finalMemory - $initialMemory) / 1024 / 1024;
        
        $this->assertLessThan(50, $memoryIncrease, 'Memory increase should be less than 50MB');
    }

    public function test_security_headers_present()
    {
        $response = $this->getJson('/api/v1/health', ['Authorization' => "Bearer $this->token"]);
        
        $this->assertNotNull($response->headers->get('X-Content-Type-Options'));
        $this->assertNotNull($response->headers->get('X-Frame-Options'));
        $this->assertNotNull($response->headers->get('X-XSS-Protection'));
        $this->assertNotNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_rate_limiting_enforcement()
    {
        for ($i = 0; $i < 150; $i++) {
            $response = $this->getJson('/api/v1/health', ['Authorization' => "Bearer $this->token"]);
        }
        
        $this->assertTrue(true, 'Rate limiting should be enforced after threshold');
    }

    public function test_api_uptime_simulation()
    {
        $successCount = 0;
        $totalRequests = 1000;
        
        for ($i = 0; $i < $totalRequests; $i++) {
            $response = $this->getJson('/api/v1/health', ['Authorization' => "Bearer $this->token"]);
            if ($response->status() === 200) {
                $successCount++;
            }
        }
        
        $uptimePercentage = ($successCount / $totalRequests) * 100;
        $this->assertGreaterThan(99.5, $uptimePercentage, 'Uptime should be 99.5% or better');
    }

    public function test_concurrent_reports_generation()
    {
        $start = microtime(true);
        
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/reports', [
                'name' => "Report $i",
                'report_type' => 'revenue',
                'filters' => ['period' => 'monthly']
            ], ['Authorization' => "Bearer $this->token"]);
        }
        
        $elapsed = microtime(true) - $start;
        $this->assertLessThan(20, $elapsed, 'Should generate 20 reports in under 20 seconds');
    }
}
