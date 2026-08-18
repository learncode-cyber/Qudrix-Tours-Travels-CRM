<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Booking;
use App\Models\BookingTraveler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class Phase3Test extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $token;
    private $customer;
    private $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->token = JWTAuth::fromUser($this->user);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '+1234567890',
            'customer_type' => 'individual',
        ]);

        $this->package = Package::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Hajj Package',
            'description' => 'Complete Hajj Package',
            'price' => 5000,
            'currency' => 'USD',
        ]);
    }

    public function test_can_create_booking()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/bookings', [
                'customer_id' => $this->customer->id,
                'package_id' => $this->package->id,
                'booking_type' => 'individual',
                'travel_date' => now()->addDays(60)->toDateString(),
                'return_date' => now()->addDays(70)->toDateString(),
                'number_of_travelers' => 1,
                'total_amount' => 5000,
                'currency' => 'USD',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data']);
    }

    public function test_can_add_traveler_to_booking()
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-001',
            'booking_type' => 'individual',
            'status' => 'pending',
            'travel_date' => now()->addDays(60),
            'return_date' => now()->addDays(70),
            'number_of_travelers' => 1,
            'total_amount' => 5000,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/travelers/add', [
                'booking_id' => $booking->id,
                'first_name' => 'Ahmed',
                'last_name' => 'Ali',
                'email' => 'ahmed@example.com',
                'phone' => '+1234567890',
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'passport_number' => 'AB123456',
                'passport_expiry' => now()->addYears(5)->toDateString(),
                'nationality' => 'BD',
                'traveler_type' => 'adult',
                'emergency_contact' => 'Ali Ahmed',
                'emergency_phone' => '+1234567890',
            ]);

        $response->assertStatus(201);
    }

    public function test_can_create_itinerary()
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-002',
            'booking_type' => 'individual',
            'status' => 'pending',
            'travel_date' => now()->addDays(60),
            'return_date' => now()->addDays(70),
            'number_of_travelers' => 1,
            'total_amount' => 5000,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/itinerary/create', [
                'booking_id' => $booking->id,
                'day_number' => 1,
                'date' => now()->addDays(60)->toDateString(),
                'location' => 'Mecca',
                'activity_type' => 'worship',
                'activity_name' => 'Arrival & Hotel Check-in',
                'start_time' => '10:00:00',
                'end_time' => '18:00:00',
                'hotel_name' => 'Hilton Mecca',
            ]);

        $response->assertStatus(201);
    }

    public function test_can_confirm_booking()
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-003',
            'booking_type' => 'individual',
            'status' => 'pending',
            'travel_date' => now()->addDays(60),
            'return_date' => now()->addDays(70),
            'number_of_travelers' => 1,
            'total_amount' => 5000,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/bookings/{$booking->id}/confirm");

        $response->assertStatus(200);
        $this->assertEquals('confirmed', $booking->fresh()->status);
    }

    public function test_can_get_booking_stats()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/bookings/stats');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_can_create_group_booking()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/groups', [
                'group_name' => 'Family Group',
                'group_leader_id' => $this->customer->id,
                'total_members' => 5,
                'description' => 'Family Hajj Group',
            ]);

        $response->assertStatus(201);
    }

    public function test_can_get_travelers_for_booking()
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-004',
            'booking_type' => 'individual',
            'status' => 'pending',
            'travel_date' => now()->addDays(60),
            'return_date' => now()->addDays(70),
            'number_of_travelers' => 1,
            'total_amount' => 5000,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/bookings/{$booking->id}/travelers");

        $response->assertStatus(200);
    }

    public function test_can_get_itinerary_for_booking()
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-005',
            'booking_type' => 'individual',
            'status' => 'pending',
            'travel_date' => now()->addDays(60),
            'return_date' => now()->addDays(70),
            'number_of_travelers' => 1,
            'total_amount' => 5000,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/bookings/{$booking->id}/itinerary");

        $response->assertStatus(200);
    }
}
