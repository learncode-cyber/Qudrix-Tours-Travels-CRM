<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\AutomationStep;
use App\Models\AutomationTemplate;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\Package;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\TicketAiTriage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase14ComplaintAutomationTest extends TestCase
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
            'name' => 'Complaint Tenant',
            'slug' => 'complaint-tenant',
            'is_active' => true,
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant P14',
            'slug' => 'other-tenant-p14',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Complaint User',
            'email' => 'complaint@example.com',
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

    private function activeAnthropicProvider(?Tenant $tenant = null): AiProvider
    {
        return AiProvider::create([
            'tenant_id' => ($tenant ?? $this->tenant)->id,
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

    private function makeCustomer(?Tenant $tenant = null): Customer
    {
        return Customer::create([
            'tenant_id' => ($tenant ?? $this->tenant)->id,
            'name' => 'Complaint Customer',
            'customer_type' => 'individual',
        ]);
    }

    private function makeBooking(?Tenant $tenant = null): Booking
    {
        $tenant = $tenant ?? $this->tenant;
        $customer = $this->makeCustomer($tenant);
        $package = Package::create(['tenant_id' => $tenant->id, 'name' => 'Test Package']);

        return Booking::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-' . uniqid(),
            'booking_type' => 'individual',
            'status' => 'confirmed',
            'travel_date' => now()->addDays(10),
            'return_date' => now()->addDays(17),
            'number_of_travelers' => 1,
            'total_amount' => 1000,
        ]);
    }

    private function makeTicket(array $overrides = []): SupportTicket
    {
        return SupportTicket::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'subject' => 'My flight was cancelled',
            'description' => 'The airline cancelled my flight and nobody has contacted me.',
            'status' => 'open',
            'priority' => 'normal',
        ], $overrides));
    }

    // --- Support Tickets ---

    public function test_support_ticket_crud_lifecycle()
    {
        $customer = $this->makeCustomer();

        $store = $this->auth()->postJson('/api/v1/support-tickets', [
            'customer_id' => $customer->id,
            'subject' => 'Refund not received',
            'description' => 'I was promised a refund two weeks ago.',
            'category' => 'billing',
            'priority' => 'high',
        ]);
        $store->assertStatus(201)->assertJsonPath('data.status', 'open');
        $ticketId = $store->json('data.id');

        $this->auth()->getJson('/api/v1/support-tickets')->assertOk();
        $this->auth()->getJson("/api/v1/support-tickets/{$ticketId}")->assertOk()
            ->assertJsonPath('data.subject', 'Refund not received');

        $this->auth()->putJson("/api/v1/support-tickets/{$ticketId}/status", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolved_at', fn ($v) => $v !== null);

        $reply = $this->auth()->postJson("/api/v1/support-tickets/{$ticketId}/reply", [
            'message' => 'We have processed your refund.',
        ]);
        $reply->assertStatus(201)->assertJsonPath('data.message', 'We have processed your refund.');

        $escalate = $this->auth()->postJson("/api/v1/support-tickets/{$ticketId}/escalate", [
            'escalated_to' => $this->user->id,
        ]);
        $escalate->assertOk()->assertJsonPath('data.escalated', true);
    }

    public function test_support_ticket_status_update_rejects_invalid_value()
    {
        $ticket = $this->makeTicket();

        $this->auth()->putJson("/api/v1/support-tickets/{$ticket->id}/status", ['status' => 'not_a_real_status'])
            ->assertStatus(422);
    }

    // --- Legacy Complaint entity ---

    public function test_complaint_crud_and_resolution_date_set_on_resolve()
    {
        $booking = $this->makeBooking();
        $customer = Customer::find($booking->customer_id);

        $store = $this->auth()->postJson('/api/v1/complaints', [
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'title' => 'Wrong hotel room type',
            'description' => 'Booked a suite, got a standard room.',
            'category' => 'accommodation',
            'priority' => 'high',
        ]);
        $store->assertStatus(201)->assertJsonPath('data.status', 'open');
        $id = $store->json('data.id');

        $this->auth()->getJson('/api/v1/complaints')->assertOk();

        $resolve = $this->auth()->putJson("/api/v1/complaints/{$id}/status", ['status' => 'resolved']);
        $resolve->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolution_date', fn ($v) => $v !== null);
    }

    // --- AI Triage ---

    public function test_triage_fails_honestly_with_no_active_provider()
    {
        $ticket = $this->makeTicket();

        $response = $this->auth()->postJson("/api/v1/support-tickets/{$ticket->id}/ai-triage");

        $response->assertStatus(502)
            ->assertJsonPath('error', fn ($msg) => str_contains($msg, 'No active AI provider is configured'));
    }

    public function test_triage_propagates_real_provider_failure_honestly()
    {
        $this->activeAnthropicProvider();
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'invalid key']], 401)]);
        $ticket = $this->makeTicket();

        $response = $this->auth()->postJson("/api/v1/support-tickets/{$ticket->id}/ai-triage");

        $response->assertStatus(502);
        $this->assertSame(0, TicketAiTriage::count());
    }

    public function test_non_critical_triage_is_stored_as_a_suggestion_and_does_not_touch_the_ticket()
    {
        $this->activeAnthropicProvider();
        $this->fakeAnthropicJson([
            'severity' => 'medium',
            'category' => 'flight_issue',
            'sentiment' => 'negative',
            'detected_issues' => ['flight delay'],
            'suggested_response' => 'We are sorry for the delay. [AGENT: CONFIRM REFUND ELIGIBILITY]',
            'suggested_resolution' => 'Offer rebooking on the next available flight.',
            'recommends_escalation' => false,
            'escalation_reason' => null,
        ]);
        $ticket = $this->makeTicket();

        $response = $this->auth()->postJson("/api/v1/support-tickets/{$ticket->id}/ai-triage");

        $response->assertStatus(201)
            ->assertJsonPath('is_suggestion', true)
            ->assertJsonPath('applied_to_ticket', false)
            ->assertJsonPath('data.suggested_severity', 'medium');

        $ticket->refresh();
        $this->assertFalse($ticket->escalated);
        $this->assertNull($ticket->priority === 'urgent' ? 'urgent' : null); // priority untouched
        $this->assertSame('normal', $ticket->priority);
    }

    public function test_critical_triage_auto_escalates_and_persists_escalation_source_and_note()
    {
        $this->activeAnthropicProvider();
        $this->fakeAnthropicJson([
            'severity' => 'critical',
            'category' => 'stranded_traveler',
            'sentiment' => 'angry',
            'detected_issues' => ['customer stranded at airport'],
            'suggested_response' => 'We are escalating this immediately. [AGENT: CONFIRM REFUND ELIGIBILITY]',
            'suggested_resolution' => 'Arrange emergency accommodation.',
            'recommends_escalation' => true,
            'escalation_reason' => 'Customer is stranded with an imminent connecting flight at risk.',
        ]);
        $ticket = $this->makeTicket();

        $response = $this->auth()->postJson("/api/v1/support-tickets/{$ticket->id}/ai-triage");
        $response->assertStatus(201)->assertJsonPath('data.suggested_severity', 'critical');

        $ticket->refresh();
        $this->assertTrue($ticket->escalated);
        $this->assertSame('ai_critical', $ticket->escalation_source);
        $this->assertSame('Customer is stranded with an imminent connecting flight at risk.', $ticket->escalation_note);
        // The directive: escalation only ADDS attention, never resolves/answers.
        $this->assertSame('open', $ticket->status);
    }

    public function test_show_lists_triage_history_and_apply_copies_suggestions_onto_the_ticket()
    {
        $this->activeAnthropicProvider();
        $this->fakeAnthropicJson([
            'severity' => 'high',
            'category' => 'billing_dispute',
            'sentiment' => 'negative',
            'detected_issues' => ['double charge'],
            'suggested_response' => 'We will review the charge. [AGENT: CONFIRM REFUND ELIGIBILITY]',
            'suggested_resolution' => 'Investigate and refund if confirmed.',
            'recommends_escalation' => false,
            'escalation_reason' => null,
        ]);
        $ticket = $this->makeTicket(['priority' => 'normal']);

        $triageResponse = $this->auth()->postJson("/api/v1/support-tickets/{$ticket->id}/ai-triage");
        $triageId = $triageResponse->json('data.id');

        $show = $this->auth()->getJson("/api/v1/support-tickets/{$ticket->id}/ai-triage");
        $show->assertOk();
        $this->assertCount(1, $show->json('data'));

        $apply = $this->auth()->postJson("/api/v1/support-tickets/{$ticket->id}/ai-triage/{$triageId}/apply");
        $apply->assertOk()
            ->assertJsonPath('data.ticket.priority', 'high')
            ->assertJsonPath('data.ticket.category', 'billing_dispute')
            ->assertJsonPath('data.triage.applied_by', $this->user->id);

        // Applying twice is rejected.
        $this->auth()->postJson("/api/v1/support-tickets/{$ticket->id}/ai-triage/{$triageId}/apply")
            ->assertStatus(422);
    }

    public function test_triage_is_tenant_scoped()
    {
        $otherTicket = SupportTicket::create([
            'tenant_id' => $this->otherTenant->id,
            'subject' => 'Other tenant ticket',
            'description' => 'Not visible to this tenant.',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->auth()->postJson("/api/v1/support-tickets/{$otherTicket->id}/ai-triage")->assertStatus(404);
        $this->auth()->getJson("/api/v1/support-tickets/{$otherTicket->id}/ai-triage")->assertStatus(404);
    }

    // --- Automation Engine ---

    private function makeAutomation(array $overrides = []): Automation
    {
        return Automation::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Welcome Task Automation',
            'trigger_type' => 'customer_added',
            'status' => 'active',
        ], $overrides));
    }

    public function test_automation_crud_lifecycle_and_trigger_type_validation()
    {
        $store = $this->auth()->postJson('/api/v1/automations', [
            'name' => 'New Booking Followup',
            'trigger_type' => 'booking_created',
            'status' => 'draft',
        ]);
        $store->assertStatus(201)->assertJsonPath('data.status', 'draft');
        $id = $store->json('data.id');

        $this->auth()->postJson('/api/v1/automations', [
            'name' => 'Bad Trigger',
            'trigger_type' => 'not_a_real_trigger',
            'status' => 'draft',
        ])->assertStatus(422);

        $this->auth()->getJson("/api/v1/automations/{$id}")->assertOk()
            ->assertJsonPath('data.name', 'New Booking Followup');

        $this->auth()->putJson("/api/v1/automations/{$id}", ['status' => 'active', 'is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_automation_execute_runs_create_task_step_and_logs_success()
    {
        $automation = $this->makeAutomation();
        AutomationStep::create([
            'automation_id' => $automation->id,
            'step_order' => 1,
            'action_type' => 'create_task',
            'action_config' => ['title' => 'Follow up with new customer', 'priority' => 'normal'],
            'delay_seconds' => 0,
        ]);

        $response = $this->auth()->postJson("/api/v1/automations/{$automation->id}/execute", [
            'trigger_data' => ['customer_id' => 1],
        ]);

        $response->assertOk()->assertJsonPath('data.1.created', true);

        $automation->refresh();
        $this->assertSame(1, $automation->run_count);
        $this->assertNotNull($automation->last_run_at);

        $log = AutomationLog::where('automation_id', $automation->id)->first();
        $this->assertSame('success', $log->status);
    }

    public function test_automation_step_condition_skips_action_when_not_met()
    {
        $automation = $this->makeAutomation();
        AutomationStep::create([
            'automation_id' => $automation->id,
            'step_order' => 1,
            'action_type' => 'create_task',
            'action_config' => ['title' => 'Only for VIP'],
            'condition_type' => 'field_check',
            'condition_config' => ['field' => 'tier', 'operator' => 'equals', 'value' => 'vip'],
            'delay_seconds' => 0,
        ]);

        $response = $this->auth()->postJson("/api/v1/automations/{$automation->id}/execute", [
            'trigger_data' => ['tier' => 'standard'],
        ]);

        $response->assertOk()->assertJsonPath('data', []);
    }

    public function test_automation_send_sms_step_honestly_reports_no_provider_configured()
    {
        $automation = $this->makeAutomation();
        AutomationStep::create([
            'automation_id' => $automation->id,
            'step_order' => 1,
            'action_type' => 'send_sms',
            'action_config' => ['phone' => '+15551234567'],
            'delay_seconds' => 0,
        ]);

        $response = $this->auth()->postJson("/api/v1/automations/{$automation->id}/execute");

        $response->assertOk()
            ->assertJsonPath('data.1.sent', false)
            ->assertJsonPath('data.1.reason', fn ($v) => str_contains($v, 'CONTRACT REQUIRED'));
    }

    public function test_automation_webhook_step_is_blocked_from_reaching_a_private_network_address()
    {
        $automation = $this->makeAutomation();
        AutomationStep::create([
            'automation_id' => $automation->id,
            'step_order' => 1,
            'action_type' => 'webhook',
            'action_config' => ['url' => 'http://127.0.0.1:9999/hook'],
            'delay_seconds' => 0,
        ]);

        $response = $this->auth()->postJson("/api/v1/automations/{$automation->id}/execute");

        $response->assertOk()
            ->assertJsonPath('data.1.status', 'error')
            ->assertJsonPath('data.1.reason', fn ($v) => str_contains($v, 'private or reserved address'));
    }

    public function test_automation_test_endpoint_reports_validity_without_running_steps()
    {
        $automation = $this->makeAutomation();
        AutomationStep::create([
            'automation_id' => $automation->id,
            'step_order' => 1,
            'action_type' => 'webhook',
            'action_config' => ['url' => 'http://127.0.0.1:9999/hook'],
        ]);

        $response = $this->auth()->postJson("/api/v1/automations/{$automation->id}/test", ['test_data' => ['x' => 1]]);

        $response->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.steps_count', 1);

        // test() never executes steps, so no webhook attempt and no log row.
        $this->assertSame(0, AutomationLog::count());
    }

    public function test_automations_are_tenant_scoped()
    {
        $otherAutomation = Automation::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other tenant automation',
            'trigger_type' => 'customer_added',
            'status' => 'active',
        ]);

        $this->auth()->getJson("/api/v1/automations/{$otherAutomation->id}")->assertStatus(404);
        $this->auth()->postJson("/api/v1/automations/{$otherAutomation->id}/execute")->assertStatus(404);
    }

    public function test_automation_logs_endpoints()
    {
        $automation = $this->makeAutomation();
        AutomationStep::create([
            'automation_id' => $automation->id,
            'step_order' => 1,
            'action_type' => 'create_task',
            'action_config' => ['title' => 'Log test task'],
        ]);
        $this->auth()->postJson("/api/v1/automations/{$automation->id}/execute")->assertOk();

        $logs = $this->auth()->getJson("/api/v1/automations/{$automation->id}/logs");
        $logs->assertOk();
        $this->assertCount(1, $logs->json('data'));

        $stats = $this->auth()->getJson("/api/v1/automations/{$automation->id}/stats");
        $stats->assertOk()
            ->assertJsonPath('data.total_runs', 1)
            ->assertJsonPath('data.success_count', 1)
            ->assertJsonPath('data.error_count', 0);

        $this->auth()->deleteJson("/api/v1/automations/{$automation->id}/logs")->assertOk();
        $this->assertSame(0, AutomationLog::where('automation_id', $automation->id)->count());
    }

    public function test_automation_dashboard_summary_and_metrics()
    {
        $automation = $this->makeAutomation(['is_active' => true]);
        AutomationStep::create([
            'automation_id' => $automation->id,
            'step_order' => 1,
            'action_type' => 'create_task',
            'action_config' => ['title' => 'Dashboard test task'],
        ]);
        $this->auth()->postJson("/api/v1/automations/{$automation->id}/execute")->assertOk();

        $summary = $this->auth()->getJson('/api/v1/automation-dashboard/summary');
        $summary->assertOk()
            ->assertJsonPath('data.total_automations', 1)
            ->assertJsonPath('data.active_automations', 1)
            ->assertJsonPath('data.total_runs', 1);

        $metrics = $this->auth()->getJson('/api/v1/automation-dashboard/metrics');
        $metrics->assertOk();
        $this->assertEquals(100.0, (float) $metrics->json('data.success_rate'));
    }

    public function test_automation_templates_index_show_category_and_use()
    {
        $template = AutomationTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Post-booking follow up',
            'category' => 'sales',
            'workflow_config' => ['steps' => [['action_type' => 'create_task']]],
            'status' => 'active',
            'usage_count' => 0,
        ]);

        $this->auth()->getJson('/api/v1/automation-templates')->assertOk()
            ->assertJsonPath('data.0.id', $template->id);

        $this->auth()->getJson("/api/v1/automation-templates/{$template->id}")->assertOk();

        $this->auth()->getJson('/api/v1/automation-templates/category/sales')->assertOk()
            ->assertJsonCount(1, 'data');

        $use = $this->auth()->postJson("/api/v1/automation-templates/{$template->id}/use");
        $use->assertOk()->assertJsonPath('data.steps.0.action_type', 'create_task');

        $template->refresh();
        $this->assertSame(1, $template->usage_count);
    }
}
