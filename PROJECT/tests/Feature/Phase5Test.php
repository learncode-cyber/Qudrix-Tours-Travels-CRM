<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\HajjPackage;
use App\Models\Complaint;
use App\Models\Booking;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * FIX (test-suite audit, 2026-09-19): this file previously imported
 * Laravel\Lumen\Testing\DatabaseMigrations — a trait from the Lumen
 * micro-framework, which isn't installed here (this is Laravel 11, not
 * Lumen; laravel/lumen-framework is not in composer.json). Declaring this
 * class would fatal on "Trait not found" before a single test could run.
 * It also generated a fake 'test_jwt_token' string instead of a real JWT,
 * so even past a fixed trait every request would have failed
 * authentication and gotten 401, not the 200/201 the tests asserted.
 * Replaced with the same RefreshDatabase + JWTAuth::fromUser() pattern
 * already used correctly in Phase1Test.php.
 */
class Phase5Test extends TestCase
{
    use RefreshDatabase;
    private $token;
    private $tenant;
    private $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Test Travel Agency', 'slug' => 'test-travel-agency', 'is_active' => true]);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@travel.com',
            'password' => Hash::make('password'),
            'is_active' => true,
            'status' => 'active',
        ]);
        $this->token = JWTAuth::fromUser($this->user);
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
