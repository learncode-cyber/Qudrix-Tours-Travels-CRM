<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Agent;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Covers MASTER_PROJECT_AUDIT.md P0: Agent/Vendor separation from Supplier,
 * and the Supplier update/destroy methods that were routed but missing.
 */
class AgentVendorSupplierTest extends TestCase
{
    use RefreshDatabase;
    private $tenant;
    private $user;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'P0 Test Agency', 'slug' => 'p0-test-agency', 'is_active' => true]);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'P0 Test User',
            'email' => 'p0@example.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'status' => 'active',
        ]);
        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_can_create_agent()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/agents', [
                'agent_code' => 'AG-001',
                'name' => 'Referral Agent One',
                'phone' => '+8801700000000',
                'commission_type' => 'percentage',
                'commission_rate' => 10,
            ]);

        $response->assertStatus(201)->assertJsonPath('data.agent_code', 'AG-001');
    }

    public function test_agent_code_must_be_unique()
    {
        Agent::create([
            'tenant_id' => $this->tenant->id, 'agent_code' => 'AG-DUP', 'name' => 'First',
            'phone' => '111', 'commission_type' => 'percentage', 'commission_rate' => 5, 'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/agents', [
                'agent_code' => 'AG-DUP', 'name' => 'Second', 'phone' => '222',
                'commission_type' => 'percentage', 'commission_rate' => 5,
            ]);

        $response->assertStatus(422);
    }

    public function test_can_create_vendor()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/vendors', [
                'name' => 'Print Shop Ltd', 'category' => 'printing',
            ]);

        $response->assertStatus(201)->assertJsonPath('data.category', 'printing');
    }

    /**
     * FIX (MASTER_PROJECT_AUDIT.md P0): routes/api.php registered a full
     * apiResource for suppliers, but SupplierController had no update or
     * destroy method — this would have 500'd. Confirms the fix actually
     * works, not just that the method now exists syntactically.
     */
    public function test_supplier_can_be_updated_and_deleted()
    {
        $supplier = Supplier::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Old Name', 'type' => 'hotel',
            'email' => 'old@supplier.com', 'phone' => '123', 'status' => 'active',
        ]);

        $updateResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/v1/suppliers/{$supplier->id}", ['name' => 'New Name']);
        $updateResponse->assertStatus(200)->assertJsonPath('data.name', 'New Name');

        $deleteResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v1/suppliers/{$supplier->id}");
        $deleteResponse->assertStatus(200);
    }

    public function test_agent_from_another_tenant_is_not_visible()
    {
        $otherTenant = Tenant::create(['name' => 'Other Agency', 'slug' => 'other-agency-p0', 'is_active' => true]);
        $otherAgent = Agent::create([
            'tenant_id' => $otherTenant->id, 'agent_code' => 'AG-OTHER', 'name' => 'Other',
            'phone' => '999', 'commission_type' => 'percentage', 'commission_rate' => 5, 'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/agents/{$otherAgent->id}");

        $response->assertStatus(404);
    }
}
