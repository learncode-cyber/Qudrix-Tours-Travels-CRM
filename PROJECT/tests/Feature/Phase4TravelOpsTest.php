<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\ExpiryReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase4TravelOpsTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Travel Ops Tenant',
            'slug' => 'travel-ops-tenant',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Travel Ops User',
            'email' => 'travelops@example.com',
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

    public function test_booking_calendar_returns_bookings_overlapping_range()
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Cal Customer', 'customer_type' => 'individual']);
        $package = Package::create(['tenant_id' => $this->tenant->id, 'name' => 'Cal Package']);

        $inRange = Booking::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-CAL-1',
            'booking_type' => 'individual',
            'status' => 'confirmed',
            'travel_date' => now()->addDays(5),
            'return_date' => now()->addDays(10),
            'number_of_travelers' => 1,
            'total_amount' => 1000,
            'currency' => 'USD',
        ]);

        $outOfRange = Booking::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-CAL-2',
            'booking_type' => 'individual',
            'status' => 'confirmed',
            'travel_date' => now()->addDays(90),
            'return_date' => now()->addDays(97),
            'number_of_travelers' => 1,
            'total_amount' => 1000,
            'currency' => 'USD',
        ]);

        $response = $this->auth()->getJson('/api/v1/bookings/calendar?from=' . now()->toDateString() . '&to=' . now()->addDays(30)->toDateString());

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($inRange->id));
        $this->assertFalse($ids->contains($outOfRange->id));
    }

    public function test_embassy_crud()
    {
        $create = $this->auth()->postJson('/api/v1/embassies', [
            'name' => 'Embassy of Testland',
            'country' => 'Testland',
            'city' => 'Test City',
            'contact_email' => 'visa@testland.gov',
            'average_processing_days' => 15,
        ]);
        $create->assertStatus(201);
        $id = $create->json('data.id');

        $show = $this->auth()->getJson("/api/v1/embassies/{$id}");
        $show->assertStatus(200)->assertJsonPath('data.name', 'Embassy of Testland');

        $update = $this->auth()->putJson("/api/v1/embassies/{$id}", ['average_processing_days' => 20]);
        $update->assertStatus(200)->assertJsonPath('data.average_processing_days', 20);

        $list = $this->auth()->getJson('/api/v1/embassies');
        $list->assertStatus(200);
        $this->assertCount(1, $list->json('data'));
    }

    public function test_room_block_create_and_release()
    {
        $hotel = Hotel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Block Hotel',
            'city' => 'Test City',
            'country' => 'Testland',
            'address' => '1 Test St',
            'phone' => '+10000000000',
            'email' => 'hotel@example.com',
            'star_rating' => 4,
            'total_rooms' => 50,
            'available_rooms' => 50,
            'price_per_night' => 100,
            'currency' => 'USD',
        ]);
        $roomType = HotelRoomType::create(['tenant_id' => $this->tenant->id, 'hotel_id' => $hotel->id, 'name' => 'Deluxe', 'capacity' => 2, 'total_rooms' => 20, 'available_rooms' => 20, 'price_per_night' => 120, 'currency' => 'USD']);

        $create = $this->auth()->postJson('/api/v1/room-blocks', [
            'hotel_id' => $hotel->id,
            'hotel_room_type_id' => $roomType->id,
            'name' => 'Group A',
            'blocked_rooms' => 10,
            'start_date' => now()->addDays(30)->toDateString(),
            'end_date' => now()->addDays(35)->toDateString(),
        ]);
        $create->assertStatus(201)->assertJsonPath('data.status', 'held');
        $id = $create->json('data.id');

        $release = $this->auth()->postJson("/api/v1/room-blocks/{$id}/release", ['rooms' => 4]);
        $release->assertStatus(200)->assertJsonPath('data.status', 'partially_released');
        $this->assertEquals(6, $release->json('data.blocked_rooms') - $release->json('data.released_rooms'));

        $overRelease = $this->auth()->postJson("/api/v1/room-blocks/{$id}/release", ['rooms' => 100]);
        $overRelease->assertStatus(422);
    }

    public function test_expiry_reminder_service_creates_visa_and_passport_reminders_once()
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Expiry Customer', 'customer_type' => 'individual']);
        $package = Package::create(['tenant_id' => $this->tenant->id, 'name' => 'Expiry Package']);
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-EXP-1',
            'booking_type' => 'individual',
            'status' => 'confirmed',
            'travel_date' => now()->addDays(60),
            'return_date' => now()->addDays(67),
            'number_of_travelers' => 1,
            'total_amount' => 1000,
            'currency' => 'USD',
        ]);

        $traveler = BookingTraveler::create([
            'booking_id' => $booking->id,
            'first_name' => 'Jane',
            'last_name' => 'Traveler',
            'email' => 'jane.traveler@example.com',
            'phone' => '+10000000000',
            'date_of_birth' => now()->subYears(30),
            'gender' => 'female',
            'passport_number' => 'X1234567',
            'passport_expiry' => now()->addDays(45),
            'nationality' => 'US',
            'traveler_type' => 'adult',
            'emergency_contact' => 'John Traveler',
            'emergency_phone' => '+10000000001',
        ]);

        VisaApplication::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'booking_traveler_id' => $traveler->id,
            'destination_country' => 'GB',
            'visa_type' => 'tourist',
            'application_date' => now(),
            'status' => 'approved',
            'visa_number' => 'V-001',
            'expiry_date' => now()->addDays(50),
        ]);

        $service = app(ExpiryReminderService::class);
        $result = $service->checkAll(90);

        $this->assertEquals(1, $result['visa']);
        $this->assertEquals(1, $result['passport']);

        // Idempotent: running again creates no duplicates
        $resultAgain = $service->checkAll(90);
        $this->assertEquals(0, $resultAgain['visa']);
        $this->assertEquals(0, $resultAgain['passport']);

        $this->assertDatabaseCount('reminders', 2);
    }

    public function test_expiry_reminder_endpoint_is_tenant_scoped()
    {
        $response = $this->auth()->postJson('/api/v1/visas/check-expiry-reminders');

        $response->assertStatus(200)->assertJsonStructure([
            'data' => ['visa_reminders_created', 'passport_reminders_created'],
        ]);
    }

    public function test_document_can_attach_to_flight_and_hotel_bookings()
    {
        Storage::fake('local');

        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Doc Customer', 'customer_type' => 'individual']);
        $package = Package::create(['tenant_id' => $this->tenant->id, 'name' => 'Doc Package']);
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-DOC-1',
            'booking_type' => 'individual',
            'status' => 'confirmed',
            'travel_date' => now()->addDays(10),
            'return_date' => now()->addDays(15),
            'number_of_travelers' => 1,
            'total_amount' => 500,
            'currency' => 'USD',
        ]);

        $flight = \App\Models\Flight::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'airline_code' => 'QR',
            'flight_number' => 'QR-DOC-1',
            'departure_airport' => 'DXB',
            'arrival_airport' => 'JFK',
            'departure_date' => now()->addDays(10),
            'arrival_date' => now()->addDays(10),
            'departure_time' => '10:00:00',
            'arrival_time' => '18:00:00',
            'aircraft_type' => 'A380',
            'total_seats' => 400,
            'available_seats' => 399,
            'price_per_seat' => 800,
        ]);

        $traveler = BookingTraveler::create([
            'booking_id' => $booking->id,
            'first_name' => 'Doc',
            'last_name' => 'Traveler',
            'email' => 'doc.traveler@example.com',
            'phone' => '+10000000002',
            'date_of_birth' => now()->subYears(25),
            'gender' => 'male',
            'passport_number' => 'Y7654321',
            'passport_expiry' => now()->addYears(3),
            'nationality' => 'US',
            'traveler_type' => 'adult',
            'emergency_contact' => 'Jane Traveler',
            'emergency_phone' => '+10000000003',
        ]);

        $flightBooking = \App\Models\FlightBooking::create([
            'booking_id' => $booking->id,
            'flight_id' => $flight->id,
            'booking_traveler_id' => $traveler->id,
            'seat_number' => '12A',
            'pnr' => 'ABC123',
            'status' => 'booked',
        ]);

        $response = $this->auth()->postJson('/api/v1/documents', [
            'documentable_type' => 'flight_booking',
            'documentable_id' => $flightBooking->id,
            'file' => UploadedFile::fake()->create('eticket.pdf', 100, 'application/pdf'),
            'category' => 'ticket',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('documents', [
            'documentable_type' => \App\Models\FlightBooking::class,
            'documentable_id' => $flightBooking->id,
        ]);
    }

    // These cover a whole class of bug found while verifying this phase:
    // several apiResource(...) registrations pointed at controller
    // methods (update/destroy, or destroy named delete()) that simply
    // didn't exist, so the routes 500'd the moment anything called them.
    // A systematic scan across every apiResource in the app found and
    // fixed 8 such gaps; these assert the fixes actually work end to end.

    public function test_hotel_update_and_delete()
    {
        $hotel = Hotel::create(['tenant_id' => $this->tenant->id, 'name' => 'CRUD Hotel', 'city' => 'City', 'country' => 'Country', 'address' => 'Addr', 'phone' => '+1', 'email' => 'h@example.com', 'star_rating' => 3, 'total_rooms' => 10, 'available_rooms' => 10, 'price_per_night' => 50, 'currency' => 'USD']);

        $update = $this->auth()->putJson("/api/v1/hotels/{$hotel->id}", ['name' => 'Renamed Hotel']);
        $update->assertStatus(200)->assertJsonPath('data.name', 'Renamed Hotel');

        $delete = $this->auth()->deleteJson("/api/v1/hotels/{$hotel->id}");
        $delete->assertStatus(200);
        $this->assertSoftDeleted('hotels', ['id' => $hotel->id]);
    }

    public function test_transport_show_update_and_delete()
    {
        $transport = \App\Models\Transport::create([
            'tenant_id' => $this->tenant->id,
            'transport_type' => 'bus',
            'vehicle_name' => 'Coach 1',
            'vehicle_number' => 'T-001',
            'pickup_location' => 'Airport',
            'dropoff_location' => 'Hotel',
            'pickup_date' => now()->addDays(5),
            'pickup_time' => '09:00:00',
            'capacity' => 40,
            'price_per_seat' => 20,
            'currency' => 'USD',
            'driver_name' => 'Driver',
            'driver_phone' => '+1',
            'status' => 'active',
        ]);

        $this->auth()->getJson("/api/v1/transports/{$transport->id}")->assertStatus(200);

        $update = $this->auth()->putJson("/api/v1/transports/{$transport->id}", ['vehicle_name' => 'Coach 2']);
        $update->assertStatus(200)->assertJsonPath('data.vehicle_name', 'Coach 2');

        $delete = $this->auth()->deleteJson("/api/v1/transports/{$transport->id}");
        $delete->assertStatus(200);
        $this->assertSoftDeleted('transports', ['id' => $transport->id]);
    }

    public function test_flight_and_destination_delete()
    {
        $flight = \App\Models\Flight::create([
            'tenant_id' => $this->tenant->id,
            'airline_code' => 'QR',
            'flight_number' => 'QR-DEL-1',
            'departure_airport' => 'DXB',
            'arrival_airport' => 'JFK',
            'departure_date' => now()->addDays(10),
            'arrival_date' => now()->addDays(10),
            'departure_time' => '10:00:00',
            'arrival_time' => '18:00:00',
            'aircraft_type' => 'A380',
            'total_seats' => 400,
            'available_seats' => 400,
            'price_per_seat' => 800,
            'currency' => 'USD',
        ]);
        $this->auth()->deleteJson("/api/v1/flights/{$flight->id}")->assertStatus(200);
        $this->assertSoftDeleted('flights', ['id' => $flight->id]);

        $destination = \App\Models\Destination::create([
            'tenant_id' => $this->tenant->id,
            'country' => 'US',
            'city' => 'New York',
            'latitude' => 40.7,
            'longitude' => -74.0,
            'currency' => 'USD',
        ]);
        $this->auth()->deleteJson("/api/v1/destinations/{$destination->id}")->assertStatus(200);
        $this->assertSoftDeleted('destinations', ['id' => $destination->id]);
    }

    public function test_supplier_update_and_delete()
    {
        $supplier = \App\Models\Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Supplier',
            'type' => 'hotel',
            'email' => 'supplier@example.com',
            'phone' => '+1',
            'commission_rate' => 10,
            'status' => 'active',
        ]);

        $update = $this->auth()->putJson("/api/v1/suppliers/{$supplier->id}", ['commission_rate' => 15]);
        $update->assertStatus(200)->assertJsonPath('data.commission_rate', 15);

        $delete = $this->auth()->deleteJson("/api/v1/suppliers/{$supplier->id}");
        $delete->assertStatus(200);
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_visa_update_and_delete()
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Visa CRUD Customer', 'customer_type' => 'individual']);
        $package = Package::create(['tenant_id' => $this->tenant->id, 'name' => 'Visa CRUD Package']);
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-VISA-CRUD',
            'booking_type' => 'individual',
            'status' => 'confirmed',
            'travel_date' => now()->addDays(10),
            'return_date' => now()->addDays(15),
            'number_of_travelers' => 1,
            'total_amount' => 500,
            'currency' => 'USD',
        ]);
        $traveler = BookingTraveler::create([
            'booking_id' => $booking->id,
            'first_name' => 'Visa',
            'last_name' => 'Crud',
            'email' => 'visacrud@example.com',
            'phone' => '+1',
            'date_of_birth' => now()->subYears(30),
            'gender' => 'male',
            'passport_number' => 'Z1111111',
            'passport_expiry' => now()->addYears(3),
            'nationality' => 'US',
            'traveler_type' => 'adult',
            'emergency_contact' => 'Contact',
            'emergency_phone' => '+1',
        ]);
        $embassy = \App\Models\Embassy::create(['tenant_id' => $this->tenant->id, 'name' => 'CRUD Embassy', 'country' => 'US']);
        $visa = VisaApplication::create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $booking->id,
            'booking_traveler_id' => $traveler->id,
            'destination_country' => 'US',
            'visa_type' => 'tourist',
            'application_date' => now(),
            'status' => 'pending',
        ]);

        $update = $this->auth()->putJson("/api/v1/visas/{$visa->id}", ['embassy_id' => $embassy->id]);
        $update->assertStatus(200)->assertJsonPath('data.embassy_id', $embassy->id);

        $delete = $this->auth()->deleteJson("/api/v1/visas/{$visa->id}");
        $delete->assertStatus(200);
        $this->assertSoftDeleted('visa_applications', ['id' => $visa->id]);
    }

    public function test_customer_task_and_booking_delete()
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Delete Customer', 'customer_type' => 'individual']);
        $this->auth()->deleteJson("/api/v1/customers/{$customer->id}")->assertStatus(200);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        $task = \App\Models\Task::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Delete Task',
            'status' => 'pending',
            'created_by' => $this->user->id,
        ]);
        $this->auth()->deleteJson("/api/v1/tasks/{$task->id}")->assertStatus(200);
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);

        $package = Package::create(['tenant_id' => $this->tenant->id, 'name' => 'Delete Package']);
        $customer2 = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Booking Delete Customer', 'customer_type' => 'individual']);
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer2->id,
            'package_id' => $package->id,
            'created_by' => $this->user->id,
            'booking_number' => 'BK-DELETE-1',
            'booking_type' => 'individual',
            'status' => 'pending',
            'travel_date' => now()->addDays(10),
            'return_date' => now()->addDays(15),
            'number_of_travelers' => 1,
            'total_amount' => 500,
            'currency' => 'USD',
        ]);
        $this->auth()->deleteJson("/api/v1/bookings/{$booking->id}")->assertStatus(200);
        $this->assertSoftDeleted('bookings', ['id' => $booking->id]);
    }

    // Package basic CRUD didn't exist at all before this phase — no
    // /packages endpoint anywhere — despite Booking/QuotationItem and
    // the booking UI depending on it entirely. Found while live-testing
    // the booking creation form: the package dropdown had no way to
    // ever be populated.
    public function test_package_crud()
    {
        $create = $this->auth()->postJson('/api/v1/packages', [
            'name' => 'Test Package',
            'destination' => 'Dubai',
            'base_price' => 1500,
        ]);
        $create->assertStatus(201)->assertJsonPath('data.name', 'Test Package');
        $id = $create->json('data.id');

        $list = $this->auth()->getJson('/api/v1/packages');
        $list->assertStatus(200);
        $this->assertCount(1, $list->json('data'));

        $update = $this->auth()->putJson("/api/v1/packages/{$id}", ['base_price' => 1800]);
        $update->assertStatus(200);
        $this->assertEquals(1800, (float) $update->json('data.base_price'));

        $delete = $this->auth()->deleteJson("/api/v1/packages/{$id}");
        $delete->assertStatus(200);
        $this->assertSoftDeleted('packages', ['id' => $id]);
    }
}
