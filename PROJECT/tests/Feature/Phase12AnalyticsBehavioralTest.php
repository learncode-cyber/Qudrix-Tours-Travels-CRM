<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Communication;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase12AnalyticsBehavioralTest extends TestCase
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
            'name' => 'Analytics Tenant',
            'slug' => 'analytics-tenant',
            'is_active' => true,
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-p12',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Analytics User',
            'email' => 'analytics@example.com',
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

    private function makeCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Analytics Customer',
            'customer_type' => 'individual',
        ], $overrides));
    }

    private function makeBooking(array $overrides = []): Booking
    {
        $customerId = $overrides['customer_id'] ?? $this->makeCustomer()->id;
        $package = \App\Models\Package::create(['tenant_id' => $this->tenant->id, 'name' => 'Test Package']);

        return Booking::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customerId,
            'package_id' => $package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-' . uniqid(),
            'booking_type' => 'individual',
            'status' => 'confirmed',
            'travel_date' => now()->addDays(10),
            'return_date' => now()->addDays(17),
            'number_of_travelers' => 1,
            'total_amount' => 1000,
            'currency' => 'USD',
        ], $overrides));
    }

    // --- Executive dashboard: real aggregation, honest nulls ---

    public function test_executive_dashboard_computes_real_revenue_and_leads()
    {
        $booking = $this->makeBooking(['total_amount' => 2000]);
        Payment::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'amount' => 1500,
            'payment_method' => 'card',
            'status' => 'completed',
        ]);
        Payment::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'amount' => 999,
            'payment_method' => 'card',
            'status' => 'pending', // must be excluded — not completed
        ]);

        Lead::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Won Lead', 'source' => 'website',
            'status' => 'won', 'priority' => 'high',
        ]);
        Lead::create([
            'tenant_id' => $this->tenant->id, 'name' => 'New Lead', 'source' => 'referral',
            'status' => 'new', 'priority' => 'medium',
        ]);

        $response = $this->auth()->getJson('/api/v1/analytics/executive-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.leads.total_leads', 2)
            ->assertJsonPath('data.leads.won_leads', 1);
        $this->assertEquals(1500.0, (float) $response->json('data.revenue.total_revenue'));
        $this->assertEquals(50.0, (float) $response->json('data.leads.conversion_rate_percent'));
    }

    public function test_executive_dashboard_reports_conversion_rate_as_null_not_zero_when_no_leads()
    {
        $response = $this->auth()->getJson('/api/v1/analytics/executive-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.leads.total_leads', 0)
            ->assertJsonPath('data.leads.conversion_rate_percent', null)
            ->assertJsonPath('data.unavailable_metrics.0', fn ($msg) => str_contains($msg, 'conversion_rate'));
    }

    public function test_executive_dashboard_is_tenant_scoped()
    {
        $otherCustomer = Customer::create(['tenant_id' => $this->otherTenant->id, 'name' => 'Other', 'customer_type' => 'individual']);
        $otherPackage = \App\Models\Package::create(['tenant_id' => $this->otherTenant->id, 'name' => 'Other Package']);
        $otherBooking = Booking::create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'package_id' => $otherPackage->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-OTHER',
            'booking_type' => 'individual',
            'status' => 'confirmed',
            'travel_date' => now()->addDays(5),
            'return_date' => now()->addDays(12),
            'number_of_travelers' => 1,
            'total_amount' => 99999,
            'currency' => 'USD',
        ]);
        Payment::create([
            'tenant_id' => $this->otherTenant->id,
            'booking_id' => $otherBooking->id,
            'amount' => 99999,
            'payment_method' => 'card',
            'status' => 'completed',
        ]);

        $response = $this->auth()->getJson('/api/v1/analytics/executive-dashboard');
        $response->assertOk();
        $this->assertEquals(0.0, (float) $response->json('data.revenue.total_revenue'));
    }

    // --- Sales pipeline (real GROUP BY) ---

    public function test_pipeline_groups_leads_by_status_with_real_values()
    {
        Lead::create(['tenant_id' => $this->tenant->id, 'name' => 'A', 'source' => 'website', 'status' => 'new', 'priority' => 'low', 'estimated_value' => 1000]);
        Lead::create(['tenant_id' => $this->tenant->id, 'name' => 'B', 'source' => 'website', 'status' => 'new', 'priority' => 'low', 'estimated_value' => 2000]);
        Lead::create(['tenant_id' => $this->tenant->id, 'name' => 'C', 'source' => 'website', 'status' => 'won', 'priority' => 'low', 'estimated_value' => 500]);

        $response = $this->auth()->getJson('/api/v1/analytics/pipeline');

        $response->assertOk();
        $rows = collect($response->json('data'))->keyBy('status');
        $this->assertEquals(2, $rows['new']['lead_count']);
        $this->assertEquals(3000.0, $rows['new']['total_estimated_value']);
        $this->assertEquals(1, $rows['won']['lead_count']);
    }

    // --- Revenue trend: gap-filled months, not silently missing ---

    public function test_revenue_trend_fills_months_with_no_revenue_as_zero()
    {
        $response = $this->auth()->getJson('/api/v1/analytics/revenue-trend?months=3');

        $response->assertOk()->assertJsonCount(3, 'data');
        foreach ($response->json('data') as $row) {
            $this->assertEquals(0.0, $row['revenue']);
            $this->assertEquals(0, $row['payment_count']);
        }
    }

    public function test_revenue_trend_places_real_payment_in_the_correct_month()
    {
        $booking = $this->makeBooking();
        Payment::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'amount' => 750,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => now(),
        ]);

        $response = $this->auth()->getJson('/api/v1/analytics/revenue-trend?months=1');

        $response->assertOk()->assertJsonPath('data.0.payment_count', 1);
        $this->assertEquals(750.0, (float) $response->json('data.0.revenue'));
    }

    // --- Behavioral metrics ---

    public function test_behavioral_metrics_computes_time_to_conversion_from_real_rows()
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Converted Lead', 'source' => 'website',
            'status' => 'won', 'priority' => 'high',
        ]);
        $lead->timestamps = false;
        $lead->created_at = now()->subDays(10);
        $lead->save();

        $booking = $this->makeBooking([
            'lead_id' => $lead->id,
            'booking_number' => 'BK-CONV',
            'total_amount' => 3000,
        ]);
        $booking->timestamps = false;
        $booking->created_at = now()->subDays(4);
        $booking->save();

        $response = $this->auth()->getJson('/api/v1/analytics/behavioral');

        $response->assertOk()->assertJsonPath('data.time_to_conversion.converted_leads', 1);
        $this->assertEquals(6.0, (float) $response->json('data.time_to_conversion.average_days'));
    }

    public function test_behavioral_metrics_reports_null_averages_when_no_data_not_zero()
    {
        $response = $this->auth()->getJson('/api/v1/analytics/behavioral');

        $response->assertOk()
            ->assertJsonPath('data.time_to_conversion.average_days', null)
            ->assertJsonPath('data.deal_value.average_value', null)
            ->assertJsonPath('data.follow_up_effectiveness.win_rate_percent', null);
    }

    public function test_behavioral_metrics_engagement_by_channel_and_customer_base()
    {
        $customer = $this->makeCustomer();
        Communication::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'type' => 'email',
            'subject' => 'x',
            'message' => 'x',
            'status' => 'sent',
            'read_at' => now(),
        ]);
        Communication::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'type' => 'email',
            'subject' => 'y',
            'message' => 'y',
            'status' => 'sent',
        ]);

        $response = $this->auth()->getJson('/api/v1/analytics/behavioral');

        $response->assertOk();
        $emailRow = collect($response->json('data.engagement_by_channel'))->firstWhere('channel', 'email');
        $this->assertEquals(2, $emailRow['messages']);
        $this->assertEquals(1, $emailRow['read']);
        $this->assertEquals(50.0, $emailRow['read_rate_percent']);
    }

    // --- Quotation funnel ---

    public function test_quotation_funnel_groups_by_status_with_real_totals()
    {
        $lead = Lead::create(['tenant_id' => $this->tenant->id, 'name' => 'x', 'source' => 'website', 'status' => 'new', 'priority' => 'low']);
        Quotation::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'created_by' => $this->user->id,
            'quotation_number' => 'QT-1',
            'share_token' => bin2hex(random_bytes(10)),
            'subject' => 'x',
            'status' => 'sent',
            'currency' => 'USD',
            'valid_until' => now()->addDays(7),
            'tax_amount' => 0,
            'discount_amount' => 0,
            'subtotal' => 500,
            'total_amount' => 500,
        ]);

        $response = $this->auth()->getJson('/api/v1/analytics/quotation-funnel');

        $response->assertOk()->assertJsonPath('data.0.status', 'sent');
        $this->assertEquals(500.0, (float) $response->json('data.0.value'));
    }

    // --- Profit & loss ---

    public function test_profit_and_loss_nets_real_income_against_real_expenses()
    {
        $booking = $this->makeBooking();
        Payment::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'amount' => 1000,
            'payment_method' => 'card',
            'status' => 'completed',
        ]);
        Expense::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'category' => 'supplier',
            'description' => 'Hotel payment',
            'amount' => 400,
            'currency' => 'USD',
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->auth()->getJson('/api/v1/analytics/executive-dashboard');

        $response->assertOk();
        $this->assertEquals(1000.0, (float) $response->json('data.profit_and_loss.income'));
        $this->assertEquals(400.0, (float) $response->json('data.profit_and_loss.expenses'));
        $this->assertEquals(600.0, (float) $response->json('data.profit_and_loss.net'));
        $this->assertEquals(60.0, (float) $response->json('data.profit_and_loss.margin_percent'));
    }
}
