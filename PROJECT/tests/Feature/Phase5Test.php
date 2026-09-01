<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\HajjPackage;
use App\Models\Complaint;
use App\Models\Booking;
use App\Models\Customer;
use Laravel\Lumen\Testing\DatabaseMigrations;

class Phase5Test extends TestCase
{
    use DatabaseMigrations;
    private $token;
    private $tenant;
    private $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Test Travel Agency', 'db_host' => 'localhost']);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@travel.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);
        $this->token = 'test_jwt_token';
    }

    public function test_create_hajj_package()
    {
        $response = $this->postJson('/api/v1/hajj', [
            'name' => 'Hajj 2024',
            'duration_days' => 14,
            'price' => 5000,
            'max_capacity' => 50,
            'rituals_included' => ['umrah', 'tawaf', 'sai', 'waquf']
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(201, $response->status());
    }

    public function test_get_hajj_packages()
    {
        HajjPackage::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Hajj 2024',
            'duration_days' => 14,
            'price' => 5000,
            'max_capacity' => 50,
            'status' => 'active'
        ]);

        $response = $this->getJson('/api/v1/hajj', ['Authorization' => "Bearer $this->token"]);
        $this->assertEquals(200, $response->status());
    }

    public function test_create_umrah_package()
    {
        $response = $this->postJson('/api/v1/umrah', [
            'name' => 'Umrah Gold',
            'duration_days' => 7,
            'price' => 2000,
            'max_capacity' => 30,
            'rituals_included' => ['umrah', 'tawaf']
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(201, $response->status());
    }

    public function test_create_tour_package()
    {
        $response = $this->postJson('/api/v1/tours', [
            'name' => 'Dubai Tour',
            'destination' => 'Dubai',
            'duration_days' => 5,
            'price' => 1500,
            'max_capacity' => 20,
            'activities' => ['desert_safari', 'city_tour', 'marina']
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(201, $response->status());
    }

    public function test_create_complaint()
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'reference_number' => 'BK001',
            'status' => 'confirmed',
            'total_price' => 5000
        ]);
        
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Ahmed',
            'email' => 'ahmed@test.com'
        ]);

        $response = $this->postJson('/api/v1/complaints', [
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'title' => 'Flight Delayed',
            'description' => 'Flight was 3 hours delayed',
            'category' => 'flight',
            'priority' => 'high'
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(201, $response->status());
    }

    public function test_update_complaint_status()
    {
        $complaint = Complaint::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Hotel Issue',
            'description' => 'Room not ready',
            'category' => 'accommodation',
            'status' => 'open'
        ]);

        $response = $this->putJson("/api/v1/complaints/{$complaint->id}/status", ['status' => 'resolved'], 
            ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(200, $response->status());
        $this->assertDatabaseHas('complaints', ['id' => $complaint->id, 'status' => 'resolved']);
    }

    public function test_add_expense()
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'reference_number' => 'BK002',
            'status' => 'confirmed',
            'total_price' => 3000
        ]);

        $response = $this->postJson('/api/v1/expenses', [
            'booking_id' => $booking->id,
            'category' => 'accommodation',
            'amount' => 500,
            'currency' => 'USD',
            'expense_date' => '2024-01-01'
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(201, $response->status());
    }

    public function test_create_supplier()
    {
        $response = $this->postJson('/api/v1/suppliers', [
            'name' => 'Emirates Airlines',
            'type' => 'airline',
            'email' => 'contact@emirates.com',
            'phone' => '+971-123-4567',
            'commission_rate' => 5.5
        ], ['Authorization' => "Bearer $this->token"]);

        $this->assertEquals(201, $response->status());
    }
}
