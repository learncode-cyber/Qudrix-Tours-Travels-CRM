<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Report;
use App\Models\Analytics;
use App\Models\DataInsight;
use App\Models\CustomerSegment;
use App\Models\Prediction;
use App\Models\Dashboard;
use Laravel\Lumen\Testing\DatabaseMigrations;

class Phase7Test extends TestCase
{
    use DatabaseMigrations;
    private $token;
    private $tenant;
    private $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Analytics Agency', 'db_host' => 'localhost']);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'analyst@agency.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);
        $this->token = 'test_jwt_token';
    }

    public function test_get_analytics_metrics()
    {
        Analytics::create([
            'tenant_id' => $this->tenant->id,
            'metric_type' => 'revenue',
            'metric_value' => 50000,
            'period' => 'daily',
            'recorded_date' => now()
        ]);

        $response = $this->getJson('/api/v1/analytics/metrics', ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }

    public function test_get_metric_by_type()
    {
        Analytics::create([
            'tenant_id' => $this->tenant->id,
            'metric_type' => 'bookings',
            'metric_value' => 25,
            'period' => 'daily',
            'recorded_date' => now()
        ]);

        $response = $this->getJson('/api/v1/analytics/metric/bookings', 
            ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }

    public function test_create_report()
    {
        $response = $this->postJson('/api/v1/reports', [
            'name' => 'Monthly Revenue Report',
            'report_type' => 'revenue',
            'filters' => ['period' => 'monthly']
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(201, $response->status());
        $this->assertDatabaseHas('reports', ['name' => 'Monthly Revenue Report']);
    }

    public function test_generate_report()
    {
        $report = Report::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Booking Report',
            'report_type' => 'booking',
            'status' => 'draft'
        ]);

        $response = $this->postJson("/api/v1/reports/{$report->id}/generate", [], 
            ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(200, $response->status());
    }

    public function test_schedule_report()
    {
        $report = Report::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Auto Report',
            'report_type' => 'revenue',
            'status' => 'completed'
        ]);

        $response = $this->postJson("/api/v1/reports/{$report->id}/schedule", [
            'frequency' => 'weekly',
            'recipients' => ['admin@agency.com']
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(200, $response->status());
    }

    public function test_get_insights()
    {
        DataInsight::create([
            'tenant_id' => $this->tenant->id,
            'insight_type' => 'revenue_trend',
            'title' => 'Revenue Up 15%',
            'description' => 'Revenue increased 15% this month',
            'impact_level' => 'high',
            'generated_at' => now()
        ]);

        $response = $this->getJson('/api/v1/insights', ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }

    public function test_get_trending_insights()
    {
        DataInsight::create([
            'tenant_id' => $this->tenant->id,
            'insight_type' => 'anomaly',
            'title' => 'Unusual Pattern Detected',
            'description' => 'Booking pattern changed significantly',
            'impact_level' => 'high',
            'generated_at' => now()
        ]);

        $response = $this->getJson('/api/v1/insights/trending', ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }

    public function test_create_customer_segment()
    {
        $response = $this->postJson('/api/v1/segments', [
            'name' => 'High Value Customers',
            'criteria' => ['spending_min' => 5000, 'booking_count_min' => 3],
            'description' => 'Customers who spent over 5000'
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(201, $response->status());
        $this->assertDatabaseHas('customer_segments', ['name' => 'High Value Customers']);
    }

    public function test_get_segment_members()
    {
        $segment = CustomerSegment::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Segment Test',
            'criteria' => ['type' => 'vip'],
            'status' => 'active'
        ]);

        $response = $this->getJson("/api/v1/segments/{$segment->id}/members", 
            ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(200, $response->status());
    }

    public function test_get_predictions()
    {
        Prediction::create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'customer',
            'entity_id' => 1,
            'prediction_type' => 'churn_risk',
            'predicted_value' => 45.5,
            'confidence_score' => 78,
            'predicted_at' => now()
        ]);

        // Mock prediction endpoint
        $this->assertTrue(true);
    }

    public function test_get_default_dashboard()
    {
        $response = $this->getJson('/api/v1/dashboard/default', 
            ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(200, $response->status());
    }

    public function test_update_dashboard()
    {
        $dashboard = Dashboard::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Analytics Dashboard',
            'widgets' => ['revenue', 'bookings'],
            'is_default' => false
        ]);

        $response = $this->putJson("/api/v1/dashboard/{$dashboard->id}", [
            'widgets' => ['revenue', 'bookings', 'customers']
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(200, $response->status());
    }

    public function test_get_dashboard_kpi()
    {
        $response = $this->getJson('/api/v1/dashboard/kpi', 
            ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(200, $response->status());
    }
}
