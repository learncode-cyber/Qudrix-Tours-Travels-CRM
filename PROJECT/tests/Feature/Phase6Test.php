<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\AutomationTemplate;
use App\Models\AutomationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Phase6Test extends TestCase
{
    use RefreshDatabase;
    private $token;
    private $tenant;
    private $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Test Agency', 'slug' => 'test-agency']);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@agency.com',
            'password' => bcrypt('password'),
        ]);
        $this->token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($this->user);
    }

    public function test_create_automation()
    {
        $response = $this->postJson('/api/v1/automations', [
            'name' => 'Send Email on Booking',
            'trigger_type' => 'booking_created',
            'status' => 'draft'
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(201, $response->status());
        $this->assertDatabaseHas('automations', ['name' => 'Send Email on Booking']);
    }

    public function test_get_automations()
    {
        Automation::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Automation',
            'trigger_type' => 'webhook',
            'status' => 'active'
        ]);

        $response = $this->getJson('/api/v1/automations', ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }

    public function test_add_automation_step()
    {
        $automation = Automation::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Email Automation',
            'trigger_type' => 'booking_created',
            'status' => 'draft'
        ]);

        AutomationStep::create([
            'automation_id' => $automation->id,
            'step_order' => 1,
            'action_type' => 'send_email',
            'action_config' => ['to' => 'user@example.com', 'subject' => 'Test'],
            'condition_type' => null,
            'condition_config' => null
        ]);

        $this->assertDatabaseHas('automation_steps', [
            'automation_id' => $automation->id,
            'action_type' => 'send_email'
        ]);
    }

    public function test_execute_automation()
    {
        $automation = Automation::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Execution',
            'trigger_type' => 'webhook',
            'status' => 'active',
            'is_active' => true
        ]);

        AutomationStep::create([
            'automation_id' => $automation->id,
            'step_order' => 1,
            'action_type' => 'create_notification',
            'action_config' => ['message' => 'Test notification'],
        ]);

        $response = $this->postJson("/api/v1/automations/{$automation->id}/execute", [
            'trigger_data' => ['booking_id' => 1]
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(200, $response->status());
        $this->assertDatabaseHas('automation_logs', ['automation_id' => $automation->id]);
    }

    public function test_test_automation()
    {
        $automation = Automation::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Automation',
            'trigger_type' => 'webhook',
            'status' => 'draft'
        ]);

        $response = $this->postJson("/api/v1/automations/{$automation->id}/test", [
            'test_data' => ['booking_id' => 1]
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(200, $response->status());
    }

    public function test_create_automation_template()
    {
        AutomationTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Email on Booking Template',
            'category' => 'email',
            'workflow_config' => [
                'trigger_type' => 'booking_created',
                'steps' => [
                    ['action_type' => 'send_email', 'action_config' => ['to' => '{customer_email}', 'subject' => 'Booking Confirmed']]
                ]
            ],
            'status' => 'active'
        ]);

        $response = $this->getJson('/api/v1/automation-templates', ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }

    public function test_get_automation_logs()
    {
        $automation = Automation::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Log Test',
            'trigger_type' => 'webhook',
            'status' => 'active'
        ]);

        AutomationLog::create([
            'automation_id' => $automation->id,
            'status' => 'success',
            'execution_time_ms' => 150,
            'started_at' => now(),
            'completed_at' => now()
        ]);

        $response = $this->getJson("/api/v1/automations/{$automation->id}/logs", ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }

    public function test_get_automation_stats()
    {
        $automation = Automation::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Stats Test',
            'trigger_type' => 'webhook',
            'status' => 'active'
        ]);

        AutomationLog::create([
            'automation_id' => $automation->id,
            'status' => 'success',
            'execution_time_ms' => 100,
            'started_at' => now()
        ]);

        $response = $this->getJson("/api/v1/automations/{$automation->id}/stats", ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }

    public function test_dashboard_summary()
    {
        Automation::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dashboard Test',
            'trigger_type' => 'webhook',
            'status' => 'active',
            'is_active' => true
        ]);

        $response = $this->getJson('/api/v1/automation-dashboard/summary', ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }

    public function test_dashboard_metrics()
    {
        $response = $this->getJson('/api/v1/automation-dashboard/metrics', ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }
}
