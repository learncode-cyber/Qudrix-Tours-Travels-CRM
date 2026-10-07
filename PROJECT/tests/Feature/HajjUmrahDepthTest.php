<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\Hotel;
use App\Models\HotelBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Covers MASTER_PROJECT_AUDIT.md P1: Mahram tracking, room assignment
 * (including the max_occupancy rejection), and installment plans
 * (including even-split-with-remainder and the completed-plan transition).
 */
class HajjUmrahDepthTest extends TestCase
{
    use RefreshDatabase;
    private $tenant;
    private $user;
    private $token;
    private $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'P1 Test Agency', 'slug' => 'p1-test-agency', 'is_active' => true]);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'P1 Test User', 'email' => 'p1@example.com',
            'password' => Hash::make('Password@123'), 'is_active' => true, 'status' => 'active',
        ]);
        $this->token = JWTAuth::fromUser($this->user);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Test Customer', 'email' => 'cust@p1.com',
            'phone' => '111', 'status' => 'active',
        ]);
        $package = Package::create(['tenant_id' => $this->tenant->id, 'name' => 'Umrah Package']);

        $this->booking = Booking::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'package_id' => $package->id,
            'created_by' => $this->user->id, 'booking_number' => 'BK-P1-001', 'booking_type' => 'individual',
            'travel_date' => now()->addDays(30), 'return_date' => now()->addDays(40),
            'number_of_travelers' => 1, 'total_amount' => 1000,
        ]);
    }

    private function makeTraveler(): BookingTraveler
    {
        return BookingTraveler::create([
            'booking_id' => $this->booking->id, 'first_name' => 'Fatima', 'last_name' => 'Rahman',
            'email' => 'fatima@p1.com', 'phone' => '222', 'date_of_birth' => '1995-01-01',
            'gender' => 'female', 'passport_number' => 'P123456', 'passport_expiry' => now()->addYears(2),
            'nationality' => 'BD', 'traveler_type' => 'adult', 'emergency_contact' => '333',
        ]);
    }

    public function test_can_record_mahram_relationship()
    {
        $traveler = $this->makeTraveler();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/mahrams', [
                'booking_traveler_id' => $traveler->id,
                'mahram_name' => 'Abdul Rahman', 'mahram_phone' => '444',
                'relationship_type' => 'husband',
            ]);

        $response->assertStatus(201)->assertJsonPath('data.relationship_type', 'husband');
    }

    public function test_mahram_requires_name_when_no_cotraveler_given()
    {
        $traveler = $this->makeTraveler();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/mahrams', [
                'booking_traveler_id' => $traveler->id,
                'relationship_type' => 'husband',
            ]);

        $response->assertStatus(422);
    }

    public function test_room_assignment_rejects_over_capacity()
    {
        $hotel = Hotel::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Test Hotel', 'city' => 'Makkah', 'country' => 'SA',
            'address' => 'Somewhere', 'phone' => '555', 'email' => 'hotel@p1.com', 'star_rating' => 4,
            'total_rooms' => 100, 'available_rooms' => 50, 'price_per_night' => 100,
        ]);
        $hotelBooking = HotelBooking::create([
            'booking_id' => $this->booking->id, 'hotel_id' => $hotel->id,
            'check_in_date' => now()->addDays(30), 'check_out_date' => now()->addDays(35),
            'number_of_rooms' => 1, 'number_of_nights' => 5, 'room_type' => 'double',
            'price_per_night' => 100, 'total_price' => 500,
        ]);

        $roomResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/room-assignments', [
                'hotel_booking_id' => $hotelBooking->id, 'room_type' => 'double', 'max_occupancy' => 2,
            ]);
        $roomId = $roomResponse->json('data.id');

        $travelerIds = [$this->makeTraveler()->id, $this->makeTraveler()->id, $this->makeTraveler()->id];

        $assignResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/room-assignments/{$roomId}/travelers", [
                'booking_traveler_ids' => $travelerIds,
            ]);

        $assignResponse->assertStatus(422);
    }

    public function test_installment_plan_splits_amount_evenly_with_remainder_absorbed()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/installment-plans', [
                'booking_id' => $this->booking->id, 'total_amount' => 1000,
                'number_of_installments' => 3, 'first_due_date' => now()->addDays(30)->toDateString(),
            ]);

        $response->assertStatus(201);
        $installments = $response->json('data.installments');
        $this->assertCount(3, $installments);
        $sum = array_sum(array_column($installments, 'amount'));
        $this->assertEquals(1000, $sum, 'Installment amounts must sum exactly to total_amount');
    }

    public function test_installment_payment_marks_plan_completed_when_all_paid()
    {
        $planResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/installment-plans', [
                'booking_id' => $this->booking->id, 'total_amount' => 500,
                'number_of_installments' => 2, 'first_due_date' => now()->addDays(10)->toDateString(),
            ]);
        $installments = $planResponse->json('data.installments');

        foreach ($installments as $installment) {
            $this->withHeader('Authorization', "Bearer {$this->token}")
                ->postJson("/api/v1/installments/{$installment['id']}/pay", ['payment_method' => 'cash']);
        }

        $planId = $planResponse->json('data.id');
        $listResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/bookings/{$this->booking->id}/installment-plans");

        $plan = collect($listResponse->json('data'))->firstWhere('id', $planId);
        $this->assertEquals('completed', $plan['status']);
    }
}
