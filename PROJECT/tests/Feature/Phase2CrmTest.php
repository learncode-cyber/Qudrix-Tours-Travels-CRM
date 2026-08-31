<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase2CrmTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'CRM Phase 2 Tenant',
            'slug' => 'crm-phase-2-tenant',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'CRM Test User',
            'email' => 'crm-phase2@example.com',
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

    public function test_can_update_and_delete_lead()
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Editable Lead',
            'source' => 'website',
            'status' => 'new',
            'priority' => 'medium',
        ]);

        $update = $this->auth()->putJson("/api/v1/leads/{$lead->id}", [
            'name' => 'Renamed Lead',
        ]);
        $update->assertStatus(200)->assertJsonPath('data.name', 'Renamed Lead');

        $delete = $this->auth()->deleteJson("/api/v1/leads/{$lead->id}");
        $delete->assertStatus(200);
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }

    public function test_can_create_and_move_deal_through_pipeline()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Deal Customer',
            'customer_type' => 'individual',
        ]);

        $create = $this->auth()->postJson('/api/v1/deals', [
            'title' => 'Dubai Package Deal',
            'customer_id' => $customer->id,
            'amount' => 5000,
            'currency' => 'USD',
            'probability' => 20,
        ]);
        $create->assertStatus(201)->assertJsonPath('data.stage', 'new');
        $dealId = $create->json('data.id');

        $stageUpdate = $this->auth()->putJson("/api/v1/deals/{$dealId}/stage", [
            'stage' => 'qualified',
        ]);
        $stageUpdate->assertStatus(200)->assertJsonPath('data.stage', 'qualified');

        $show = $this->auth()->getJson("/api/v1/deals/{$dealId}");
        $show->assertStatus(200);
        $this->assertCount(2, $show->json('stage_history'));

        $pipeline = $this->auth()->getJson('/api/v1/deals/pipeline');
        $pipeline->assertStatus(200)->assertJsonStructure(['data' => ['new', 'qualified', 'proposal', 'negotiation', 'won', 'lost'], 'pipeline_value']);
        $this->assertEquals(1, $pipeline->json('data.qualified.count'));
    }

    public function test_deal_update_rejects_stage_field()
    {
        $deal = Deal::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Locked Stage Deal',
            'amount' => 1000,
            'currency' => 'USD',
            'stage' => 'new',
        ]);

        $response = $this->auth()->putJson("/api/v1/deals/{$deal->id}", [
            'stage' => 'won',
        ]);

        $response->assertStatus(422);
    }

    public function test_customer_360_profile_aggregates_related_data()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => '360 Customer',
            'customer_type' => 'individual',
        ]);

        Lead::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'name' => 'Linked Lead',
            'source' => 'referral',
            'status' => 'new',
            'priority' => 'low',
        ]);

        Deal::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Linked Deal',
            'amount' => 2000,
            'currency' => 'USD',
            'stage' => 'new',
        ]);

        $response = $this->auth()->getJson("/api/v1/customers/{$customer->id}/360");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'customer', 'leads', 'deals', 'bookings', 'quotations',
                'communications', 'notes', 'tags', 'timeline',
            ]]);
        $this->assertCount(1, $response->json('data.leads'));
        $this->assertCount(1, $response->json('data.deals'));
    }

    public function test_crm_dashboard_returns_kpis()
    {
        Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dashboard Lead',
            'source' => 'website',
            'status' => 'won',
            'priority' => 'high',
            'estimated_value' => 3000,
        ]);

        $response = $this->auth()->getJson('/api/v1/crm/dashboard');

        $response->assertStatus(200)->assertJsonStructure(['data' => [
            'total_leads', 'new_leads_this_month', 'conversion_rate',
            'pipeline_value_by_stage', 'deals_won', 'deals_lost',
            'tasks_due_today', 'upcoming_follow_ups',
        ]]);
        $this->assertEquals(100, $response->json('data.conversion_rate'));
    }

    public function test_conversion_funnel_reports_stage_counts()
    {
        Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Funnel Lead',
            'source' => 'website',
            'status' => 'qualified',
            'priority' => 'medium',
        ]);

        $response = $this->auth()->getJson('/api/v1/crm/conversion-funnel');

        $response->assertStatus(200)->assertJsonStructure(['data' => ['stages', 'total_leads', 'won', 'conversion_rate']]);
        $this->assertEquals(1, $response->json('data.total_leads'));
    }

    public function test_follow_up_calendar_returns_events_in_range()
    {
        Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Calendar Lead',
            'source' => 'website',
            'status' => 'new',
            'priority' => 'medium',
            'follow_up_date' => now()->addDays(3),
        ]);

        $response = $this->auth()->getJson('/api/v1/crm/follow-ups/calendar');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_sales_activity_history_is_listed()
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Activity Lead',
            'source' => 'website',
            'status' => 'new',
            'priority' => 'medium',
        ]);

        $this->auth()->postJson('/api/v1/pipeline/activity', [
            'lead_id' => $lead->id,
            'activity_type' => 'call',
            'title' => 'Intro call',
            'activity_date' => now()->toDateString(),
        ])->assertStatus(201);

        $response = $this->auth()->getJson('/api/v1/pipeline/sales-activities');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }
}
