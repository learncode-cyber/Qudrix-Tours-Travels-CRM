<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Flight;
use App\Models\Hotel;
use App\Models\Transport;
use App\Models\Destination;
use App\Models\BookingTraveler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class Phase4Test extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $token;
    private $booking;
    private $traveler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'User',
            'email' => 'user@test.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'status' => 'active',
        ]);
        $this->token = JWTAuth::fromUser($this->user);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Customer',
            'email' => 'cust@test.com',
            'phone' => '+1234567890',
            'customer_type' => 'individual',
        ]);

        $package = Package::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Package',
            'description' => 'Test Package',
            'price' => 5000,
            'currency' => 'USD',
        ]);

        $this->booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-001',
            'booking_type' => 'individual',
            'status' => 'pending',
            'travel_date' => now()->addDays(60),
            'return_date' => now()->addDays(70),
            'number_of_travelers' => 1,
            'total_amount' => 5000,
        ]);

        $this->traveler = BookingTraveler::create([
            'booking_id' => $this->booking->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Ali',
            'email' => 'ahmed@test.com',
            'phone' => '+1234567890',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'passport_number' => 'AB123456',
            'passport_expiry' => now()->addYears(5),
            'nationality' => 'BD',
            'traveler_type' => 'adult',
            'emergency_contact' => 'Contact',
            'emergency_phone' => '+1234567890',
        ]);
    }

    public function test_can_create_flight()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/flights', [
                'airline_code' => 'BA',
                'flight_number' => 'BA123',
                'departure_airport' => 'DAC',
                'arrival_airport' => 'JED',
                'departure_date' => now()->addDays(60),
                'arrival_date' => now()->addDays(60),
                'departure_time' => '10:00:00',
                'arrival_time' => '18:00:00',
                'aircraft_type' => 'Boeing 777',
                'total_seats' => 300,
                'price_per_seat' => 800,
                'currency' => 'USD',
            ]);

        $response->assertStatus(201);
    }

    public function test_can_book_flight()
    {
        $flight = Flight::create([
            'tenant_id' => $this->tenant->id,
            'airline_code' => 'BA',
            'flight_number' => 'BA456',
            'departure_airport' => 'DAC',
            'arrival_airport' => 'JED',
            'departure_date' => now()->addDays(60),
            'arrival_date' => now()->addDays(60),
            'departure_time' => '10:00:00',
            'arrival_time' => '18:00:00',
            'aircraft_type' => 'Boeing 777',
            'total_seats' => 300,
            'available_seats' => 300,
            'price_per_seat' => 800,
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/flights/book', [
                'flight_id' => $flight->id,
                'booking_id' => $this->booking->id,
                'travelers' => [$this->traveler->id],
            ]);

        $response->assertStatus(201);
    }

    public function test_can_create_hotel()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/hotels', [
                'name' => 'Hilton',
                'city' => 'Mecca',
                'country' => 'SA',
                'address' => '123 Street',
                'phone' => '+966123456',
                'email' => 'hotel@hilton.com',
                'star_rating' => 5,
                'total_rooms' => 500,
                'price_per_night' => 250,
                'currency' => 'USD',
            ]);

        $response->assertStatus(201);
    }

    public function test_can_book_hotel()
    {
        $hotel = Hotel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Hilton',
            'city' => 'Mecca',
            'country' => 'SA',
            'address' => '123 Street',
            'phone' => '+966123456',
            'email' => 'hotel@hilton.com',
            'star_rating' => 5,
            'total_rooms' => 500,
            'available_rooms' => 500,
            'price_per_night' => 250,
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/hotels/book', [
                'hotel_id' => $hotel->id,
                'booking_id' => $this->booking->id,
                'check_in_date' => now()->addDays(60),
                'check_out_date' => now()->addDays(65),
                'number_of_rooms' => 1,
                'room_type' => 'double',
            ]);

        $response->assertStatus(201);
    }

    public function test_can_create_transport()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/transports', [
                'transport_type' => 'bus',
                'vehicle_name' => 'Coach Bus',
                'vehicle_number' => 'BUS123',
                'pickup_location' => 'Airport',
                'dropoff_location' => 'Hotel',
                'pickup_date' => now()->addDays(60),
                'pickup_time' => '09:00:00',
                'capacity' => 50,
                'price_per_seat' => 50,
                'currency' => 'USD',
                'driver_name' => 'Ahmed',
                'driver_phone' => '+966123456',
            ]);

        $response->assertStatus(201);
    }

    public function test_can_create_destination()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/destinations', [
                'country' => 'SA',
                'city' => 'Mecca',
                'latitude' => 21.4225,
                'longitude' => 39.8262,
                'currency' => 'SAR',
                'visa_required' => true,
            ]);

        $response->assertStatus(201);
    }

    public function test_can_create_visa_application()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/visas', [
                'booking_id' => $this->booking->id,
                'booking_traveler_id' => $this->traveler->id,
                'destination_country' => 'SA',
                'visa_type' => 'hajj',
            ]);

        $response->assertStatus(201);
    }
}
