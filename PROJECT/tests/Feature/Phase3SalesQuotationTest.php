<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase3SalesQuotationTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Sales Phase 3 Tenant',
            'slug' => 'sales-phase-3-tenant',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sales Test User',
            'email' => 'sales-phase3@example.com',
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

    public function test_winning_a_lead_creates_and_links_a_customer()
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Won Lead',
            'email' => 'won-lead@example.com',
            'source' => 'website',
            'status' => 'qualified',
            'priority' => 'high',
        ]);

        $response = $this->auth()->putJson("/api/v1/leads/{$lead->id}/status", ['status' => 'won']);

        $response->assertStatus(200);
        $customerId = $response->json('data.customer_id');
        $this->assertNotNull($customerId);
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'email' => 'won-lead@example.com']);
    }

    public function test_winning_a_lead_reuses_existing_customer_by_email_no_duplicate()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Existing Customer',
            'email' => 'shared@example.com',
            'customer_type' => 'individual',
        ]);

        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Duplicate Risk Lead',
            'email' => 'shared@example.com',
            'source' => 'referral',
            'status' => 'qualified',
            'priority' => 'medium',
        ]);

        $response = $this->auth()->putJson("/api/v1/leads/{$lead->id}/status", ['status' => 'won']);

        $response->assertStatus(200);
        $this->assertEquals($customer->id, $response->json('data.customer_id'));
        $this->assertEquals(1, Customer::where('tenant_id', $this->tenant->id)->where('email', 'shared@example.com')->count());
    }

    public function test_pipeline_stage_update_to_won_also_links_customer()
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pipeline Won Lead',
            'email' => 'pipeline-won@example.com',
            'source' => 'website',
            'status' => 'proposal',
            'priority' => 'medium',
        ]);

        $response = $this->auth()->putJson('/api/v1/pipeline/stage', [
            'lead_id' => $lead->id,
            'new_stage' => 'won',
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.customer_id'));
    }

    public function test_accepted_quotation_converts_to_booking_without_duplicate_customer()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Conversion Customer',
            'customer_type' => 'individual',
        ]);

        $package = Package::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dubai Package',
        ]);

        $quotation = Quotation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'quotation_number' => 'QT-TEST-001',
            'subject' => 'Dubai Trip',
            'status' => 'accepted',
            'currency' => 'USD',
            'subtotal' => 2000,
            'total_amount' => 2000,
            'valid_until' => now()->addDays(30),
        ]);

        $quotation->items()->create([
            'package_id' => $package->id,
            'description' => 'Dubai Package x1',
            'quantity' => 1,
            'unit_price' => 2000,
            'tax_rate' => 0,
            'discount' => 0,
            'total' => 2000,
        ]);

        $response = $this->auth()->postJson("/api/v1/quotations/{$quotation->id}/convert-to-booking", [
            'booking_type' => 'individual',
            'travel_date' => now()->addDays(30)->toDateString(),
            'return_date' => now()->addDays(37)->toDateString(),
            'number_of_travelers' => 2,
        ]);

        $response->assertStatus(201);
        $this->assertEquals($customer->id, $response->json('data.customer_id'));
        $this->assertEquals(1, Customer::where('tenant_id', $this->tenant->id)->count());
        $this->assertDatabaseHas('bookings', ['customer_id' => $customer->id, 'package_id' => $package->id, 'total_amount' => 2000]);
    }

    public function test_draft_quotation_cannot_convert_to_booking()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Draft Customer',
            'customer_type' => 'individual',
        ]);

        $quotation = Quotation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'quotation_number' => 'QT-TEST-002',
            'subject' => 'Draft Trip',
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'valid_until' => now()->addDays(30),
        ]);

        $response = $this->auth()->postJson("/api/v1/quotations/{$quotation->id}/convert-to-booking", [
            'booking_type' => 'individual',
            'travel_date' => now()->addDays(30)->toDateString(),
            'return_date' => now()->addDays(37)->toDateString(),
            'number_of_travelers' => 1,
            'package_id' => null,
        ]);

        $response->assertStatus(400);
    }

    public function test_quotation_generates_invoice_with_matching_items()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Invoice Customer',
            'customer_type' => 'individual',
        ]);

        $quotation = Quotation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'quotation_number' => 'QT-TEST-003',
            'subject' => 'Invoice Trip',
            'status' => 'accepted',
            'currency' => 'USD',
            'subtotal' => 1500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 1500,
            'valid_until' => now()->addDays(30),
        ]);

        $quotation->items()->create([
            'description' => 'Trip package',
            'quantity' => 1,
            'unit_price' => 1500,
            'tax_rate' => 0,
            'discount' => 0,
            'total' => 1500,
        ]);

        $response = $this->auth()->postJson("/api/v1/quotations/{$quotation->id}/generate-invoice", [
            'due_date' => now()->addDays(14)->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertEquals($customer->id, $response->json('data.customer_id'));
        $this->assertEquals(1500, $response->json('data.total_amount'));
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_quotation_generates_invoice_with_no_body_defaults_due_date()
    {
        // The frontend's one-click "Generate Invoice" button sends no
        // request body at all — due_date must have a sane default.
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'No Body Customer',
            'customer_type' => 'individual',
        ]);

        $quotation = Quotation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'quotation_number' => 'QT-TEST-006',
            'subject' => 'No Body Trip',
            'status' => 'accepted',
            'currency' => 'USD',
            'subtotal' => 900,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 900,
            'valid_until' => now()->addDays(30),
        ]);

        $quotation->items()->create([
            'description' => 'Trip package',
            'quantity' => 1,
            'unit_price' => 900,
            'tax_rate' => 0,
            'discount' => 0,
            'total' => 900,
        ]);

        $response = $this->auth()->postJson("/api/v1/quotations/{$quotation->id}/generate-invoice");

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.due_date'));
    }

    public function test_quotation_pdf_and_invoice_pdf_download()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'PDF Customer',
            'customer_type' => 'individual',
        ]);

        $quotation = Quotation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'quotation_number' => 'QT-TEST-004',
            'subject' => 'PDF Trip',
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal' => 500,
            'total_amount' => 500,
            'valid_until' => now()->addDays(30),
        ]);

        $pdfResponse = $this->auth()->get("/api/v1/quotations/{$quotation->id}/pdf");
        $pdfResponse->assertStatus(200);
        $pdfResponse->assertHeader('content-type', 'application/pdf');

        $invoiceResponse = $this->auth()->postJson("/api/v1/quotations/{$quotation->id}/generate-invoice", [
            'due_date' => now()->addDays(14)->toDateString(),
        ]);
        $invoiceId = $invoiceResponse->json('data.id');

        $invoicePdfResponse = $this->auth()->get("/api/v1/invoices/{$invoiceId}/pdf");
        $invoicePdfResponse->assertStatus(200);
        $invoicePdfResponse->assertHeader('content-type', 'application/pdf');
    }

    public function test_quotation_for_won_lead_auto_links_customer_and_appears_in_history()
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'History Lead',
            'email' => 'history-lead@example.com',
            'source' => 'website',
            'status' => 'won',
            'priority' => 'medium',
        ]);
        $this->auth()->putJson("/api/v1/leads/{$lead->id}/status", ['status' => 'won']);
        $customerId = Lead::find($lead->id)->customer_id;
        $this->assertNotNull($customerId);

        $response = $this->auth()->postJson('/api/v1/quotations', [
            'lead_id' => $lead->id,
            'subject' => 'Auto-linked Quotation',
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
            'items' => [
                ['description' => 'Trip', 'quantity' => 1, 'unit_price' => 800, 'tax_rate' => 0, 'discount' => 0],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals($customerId, $response->json('data.customer_id'));

        $history = $this->auth()->getJson("/api/v1/customers/{$customerId}/quotations");
        $history->assertStatus(200);
        $this->assertCount(1, $history->json('data'));
    }

    public function test_existing_quotation_backfilled_when_lead_wins_later()
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Backfill Lead',
            'email' => 'backfill-lead@example.com',
            'source' => 'website',
            'status' => 'proposal',
            'priority' => 'medium',
        ]);

        $this->auth()->postJson('/api/v1/quotations', [
            'lead_id' => $lead->id,
            'subject' => 'Pre-win Quotation',
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
            'items' => [
                ['description' => 'Trip', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0, 'discount' => 0],
            ],
        ])->assertStatus(201);

        $this->assertDatabaseHas('quotations', ['lead_id' => $lead->id, 'customer_id' => null]);

        $this->auth()->putJson("/api/v1/leads/{$lead->id}/status", ['status' => 'won'])->assertStatus(200);
        $customerId = Lead::find($lead->id)->customer_id;

        $this->assertDatabaseHas('quotations', ['lead_id' => $lead->id, 'customer_id' => $customerId]);
    }

    public function test_sales_dashboard_returns_kpis()
    {
        $response = $this->auth()->getJson('/api/v1/sales/dashboard');

        $response->assertStatus(200)->assertJsonStructure(['data' => [
            'revenue_this_month', 'quotation_conversion_rate', 'invoice_collection_rate',
            'outstanding_amount', 'top_packages',
        ]]);
    }

    public function test_customer_quotation_history_endpoint()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'History Customer',
            'customer_type' => 'individual',
        ]);

        Quotation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'quotation_number' => 'QT-TEST-005',
            'subject' => 'History Trip',
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal' => 100,
            'total_amount' => 100,
            'valid_until' => now()->addDays(30),
        ]);

        $response = $this->auth()->getJson("/api/v1/customers/{$customer->id}/quotations");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
