<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Booking;
use App\Models\Agent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Covers MASTER_PROJECT_AUDIT.md P2: tracking config, conversion events
 * (honestly resolving to not_configured without real credentials — this
 * test asserts that honest behavior, not a fabricated 'sent' status), and
 * the commission ledger's auto-earn-on-payment + payout-limit behavior.
 */
class MarketingAndCommissionTest extends TestCase
{
    use RefreshDatabase;
    private $tenant;
    private $user;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'P2 Test Agency', 'slug' => 'p2-test-agency', 'is_active' => true]);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'P2 Test User', 'email' => 'p2@example.com',
            'password' => Hash::make('Password@123'), 'is_active' => true, 'status' => 'active',
        ]);
        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_tracking_config_can_be_saved_and_secrets_hidden()
    {
        $upsert = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson('/api/v1/tracking-config', [
                'meta_pixel_id' => '123456', 'meta_conversions_api_token' => 'secret-token',
            ]);
        $upsert->assertStatus(200);
        $upsert->assertJsonMissing(['meta_conversions_api_token' => 'secret-token']);
    }

    /**
     * Without any TrackingConfig row, an event must honestly resolve to
     * 'not_configured' — never a fabricated 'sent'.
     */
    public function test_conversion_event_without_config_is_not_configured()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/conversion-events', ['event_name' => 'qualified_lead']);

        $response->assertStatus(201)
            ->assertJsonPath('data.meta_status', 'not_configured')
            ->assertJsonPath('data.ga4_status', 'not_configured');
    }

    public function test_agent_commission_auto_earns_on_completed_payment()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Test Customer', 'email' => 'cust@p2.com',
            'phone' => '111', 'status' => 'active',
        ]);
        $package = Package::create(['tenant_id' => $this->tenant->id, 'name' => 'Test Package']);
        $agent = Agent::create([
            'tenant_id' => $this->tenant->id, 'agent_code' => 'AG-P2', 'name' => 'Commission Agent',
            'phone' => '222', 'commission_type' => 'percentage', 'commission_rate' => 10, 'status' => 'active',
        ]);
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'package_id' => $package->id,
            'agent_id' => $agent->id, 'created_by' => $this->user->id, 'booking_number' => 'BK-P2-001',
            'booking_type' => 'individual', 'travel_date' => now()->addDays(30), 'return_date' => now()->addDays(40),
            'number_of_travelers' => 1, 'total_amount' => 1000,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/payments', [
                'booking_id' => $booking->id, 'amount' => 1000, 'payment_method' => 'cash', 'status' => 'completed',
            ]);
        $response->assertStatus(201);

        $agent->refresh();
        $this->assertEquals(100.0, (float) $agent->total_commission_earned, '10% of 1000 should be earned');
    }

    public function test_commission_payout_cannot_exceed_outstanding_balance()
    {
        $agent = Agent::create([
            'tenant_id' => $this->tenant->id, 'agent_code' => 'AG-P2B', 'name' => 'Balance Test Agent',
            'phone' => '333', 'commission_type' => 'percentage', 'commission_rate' => 10,
            'total_commission_earned' => 50, 'total_commission_paid' => 0, 'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/agents/{$agent->id}/pay-commission", ['amount' => 100]);

        $response->assertStatus(422);
    }
}
