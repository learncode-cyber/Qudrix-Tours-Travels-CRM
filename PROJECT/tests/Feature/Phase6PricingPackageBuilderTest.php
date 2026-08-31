<?php

namespace Tests\Feature;

use App\Models\Flight;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\Lead;
use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase6PricingPackageBuilderTest extends TestCase
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
            'name' => 'Pricing Tenant',
            'slug' => 'pricing-tenant',
            'is_active' => true,
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-p6',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pricing User',
            'email' => 'pricing@example.com',
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

    private function makeHotelRoomType(array $overrides = []): HotelRoomType
    {
        $hotel = Hotel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Hotel',
            'city' => 'Dubai',
            'country' => 'AE',
            'address' => '123 Test St',
            'phone' => '+971500000000',
            'email' => 'hotel@example.com',
            'star_rating' => 4,
            'total_rooms' => 50,
            'available_rooms' => 50,
            'price_per_night' => 150,
            'currency' => 'USD',
            'status' => 'active',
        ]);

        return HotelRoomType::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'hotel_id' => $hotel->id,
            'name' => 'Deluxe',
            'capacity' => 2,
            'total_rooms' => 10,
            'available_rooms' => 10,
            'price_per_night' => 200,
            'currency' => 'USD',
        ], $overrides));
    }

    private function makeFlight(array $overrides = []): Flight
    {
        return Flight::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'airline_code' => 'EK',
            'flight_number' => 'EK202',
            'departure_airport' => 'DXB',
            'arrival_airport' => 'JFK',
            'departure_date' => now()->addMonths(6),
            'arrival_date' => now()->addMonths(6),
            'departure_time' => '08:00:00',
            'arrival_time' => '14:00:00',
            'aircraft_type' => 'A380',
            'total_seats' => 200,
            'available_seats' => 200,
            'price_per_seat' => 900,
            'currency' => 'USD',
            'status' => 'active',
        ], $overrides));
    }

    private function makeTransport(array $overrides = []): Transport
    {
        return Transport::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'transport_type' => 'van',
            'vehicle_name' => 'Airport Van',
            'vehicle_number' => 'DXB-1234',
            'pickup_location' => 'DXB Airport',
            'dropoff_location' => 'Hotel',
            'pickup_date' => now()->addMonths(6),
            'pickup_time' => '09:00:00',
            'capacity' => 8,
            'price_per_seat' => 50,
            'currency' => 'USD',
            'driver_name' => 'Ali',
            'driver_phone' => '+971500000001',
            'status' => 'active',
        ], $overrides));
    }

    // --- Pricing rules ---

    public function test_pricing_rule_crud_lifecycle()
    {
        $create = $this->auth()->postJson('/api/v1/pricing-rules', [
            'name' => 'Small Group Surcharge',
            'factor' => 'group_size',
            'min_group_size' => 1,
            'max_group_size' => 3,
            'adjustment_type' => 'percentage',
            'adjustment_value' => 15,
            'priority' => 1,
        ]);
        $create->assertStatus(201)->assertJsonPath('data.is_active', true);
        $id = $create->json('data.id');

        $this->auth()->getJson('/api/v1/pricing-rules')->assertOk()->assertJsonCount(1, 'data');

        $update = $this->auth()->putJson("/api/v1/pricing-rules/{$id}", ['is_active' => false]);
        $update->assertOk()->assertJsonPath('data.is_active', false);

        $this->auth()->deleteJson("/api/v1/pricing-rules/{$id}")->assertOk();
        $this->auth()->getJson('/api/v1/pricing-rules')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_pricing_rules_are_tenant_scoped()
    {
        PricingRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mine',
            'factor' => 'demand',
            'adjustment_type' => 'fixed',
            'adjustment_value' => 100,
            'priority' => 1,
            'is_active' => true,
        ]);
        PricingRule::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Not Mine',
            'factor' => 'demand',
            'adjustment_type' => 'fixed',
            'adjustment_value' => 200,
            'priority' => 1,
            'is_active' => true,
        ]);

        $response = $this->auth()->getJson('/api/v1/pricing-rules');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('Mine', $response->json('data.0.name'));
    }

    public function test_preview_applies_matching_percentage_rule()
    {
        PricingRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Small Group Surcharge',
            'factor' => 'group_size',
            'min_group_size' => 1,
            'max_group_size' => 3,
            'adjustment_type' => 'percentage',
            'adjustment_value' => 15,
            'priority' => 1,
            'is_active' => true,
        ]);

        $response = $this->auth()->postJson('/api/v1/pricing-rules/preview', [
            'base_cost' => 1000,
            'group_size' => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.final_price', 1150)
            ->assertJsonCount(1, 'data.applied_rules');
    }

    public function test_preview_skips_non_matching_rule_and_inactive_rule()
    {
        PricingRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Large Group Discount',
            'factor' => 'group_size',
            'min_group_size' => 10,
            'adjustment_type' => 'percentage',
            'adjustment_value' => -10,
            'priority' => 1,
            'is_active' => true,
        ]);
        PricingRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive Rule',
            'factor' => 'demand',
            'adjustment_type' => 'fixed',
            'adjustment_value' => 500,
            'priority' => 2,
            'is_active' => false,
        ]);

        $response = $this->auth()->postJson('/api/v1/pricing-rules/preview', [
            'base_cost' => 1000,
            'group_size' => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.final_price', 1000)
            ->assertJsonCount(0, 'data.applied_rules');
    }

    public function test_preview_applies_rules_in_priority_order_compounding()
    {
        PricingRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'First: +10%',
            'factor' => 'demand',
            'adjustment_type' => 'percentage',
            'adjustment_value' => 10,
            'priority' => 1,
            'is_active' => true,
        ]);
        PricingRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Second: +50 fixed',
            'factor' => 'demand',
            'adjustment_type' => 'fixed',
            'adjustment_value' => 50,
            'priority' => 2,
            'is_active' => true,
        ]);

        $response = $this->auth()->postJson('/api/v1/pricing-rules/preview', ['base_cost' => 1000]);

        // 1000 -> +10% = 1100 -> +50 fixed = 1150
        $response->assertOk()->assertJsonPath('data.final_price', 1150);
    }

    // --- Package builder ---

    public function test_build_resolves_real_inventory_and_computes_cost()
    {
        $roomType = $this->makeHotelRoomType();
        $flight = $this->makeFlight();
        $transport = $this->makeTransport();

        $response = $this->auth()->postJson('/api/v1/package-builder/build', [
            'destination' => 'Dubai',
            'travel_date' => now()->addMonths(6)->toDateString(),
            'group_size' => 2,
            'components' => [
                ['type' => 'hotel', 'reference_id' => $roomType->id, 'quantity' => 3],
                ['type' => 'flight', 'reference_id' => $flight->id, 'quantity' => 2],
                ['type' => 'transport', 'reference_id' => $transport->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonCount(3, 'data.components');
        $this->assertEquals(2500.0, (float) $response->json('data.pricing.base_cost'));
        $this->assertArrayNotHasKey('package', $response->json('data'));
        $this->assertArrayNotHasKey('quotation', $response->json('data'));
    }

    public function test_build_rejects_component_from_another_tenant()
    {
        $foreignRoomType = HotelRoomType::create([
            'tenant_id' => $this->otherTenant->id,
            'hotel_id' => Hotel::create([
                'tenant_id' => $this->otherTenant->id,
                'name' => 'Foreign Hotel',
                'city' => 'Paris',
                'country' => 'FR',
                'address' => '1 Rue Test',
                'phone' => '+330000000',
                'email' => 'foreign@example.com',
                'star_rating' => 3,
                'total_rooms' => 20,
                'available_rooms' => 20,
                'price_per_night' => 100,
                'currency' => 'EUR',
                'status' => 'active',
            ])->id,
            'name' => 'Standard',
            'capacity' => 2,
            'total_rooms' => 5,
            'available_rooms' => 5,
            'price_per_night' => 100,
            'currency' => 'EUR',
        ]);

        $response = $this->auth()->postJson('/api/v1/package-builder/build', [
            'destination' => 'Paris',
            'travel_date' => now()->addMonths(6)->toDateString(),
            'group_size' => 2,
            'components' => [
                ['type' => 'hotel', 'reference_id' => $foreignRoomType->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('components');
    }

    public function test_build_rejects_insufficient_flight_seats()
    {
        $flight = $this->makeFlight(['available_seats' => 1]);

        $response = $this->auth()->postJson('/api/v1/package-builder/build', [
            'destination' => 'Dubai',
            'travel_date' => now()->addMonths(6)->toDateString(),
            'group_size' => 2,
            'components' => [
                ['type' => 'flight', 'reference_id' => $flight->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('components');
    }

    public function test_build_with_save_as_package_creates_real_package()
    {
        $transport = $this->makeTransport();

        $response = $this->auth()->postJson('/api/v1/package-builder/build', [
            'destination' => 'Dubai',
            'travel_date' => now()->addMonths(6)->toDateString(),
            'group_size' => 4,
            'components' => [
                ['type' => 'transport', 'reference_id' => $transport->id, 'quantity' => 4],
            ],
            'save_as_package' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.package.is_custom_built', true)
            ->assertJsonPath('data.package.type', 'custom')
            ->assertJsonPath('data.package.destination', 'Dubai');

        $this->assertDatabaseHas('packages', [
            'destination' => 'Dubai',
            'is_custom_built' => 1,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_build_with_create_quotation_requires_lead_id()
    {
        $transport = $this->makeTransport();

        $response = $this->auth()->postJson('/api/v1/package-builder/build', [
            'destination' => 'Dubai',
            'travel_date' => now()->addMonths(6)->toDateString(),
            'group_size' => 1,
            'components' => [
                ['type' => 'transport', 'reference_id' => $transport->id, 'quantity' => 1],
            ],
            'create_quotation' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('lead_id');
    }

    public function test_build_with_create_quotation_generates_real_quotation_with_items()
    {
        $transport = $this->makeTransport();
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Quote Lead',
            'email' => 'quotelead@example.com',
            'phone' => '+15550001111',
            'source' => 'website',
            'status' => 'new',
            'priority' => 'medium',
        ]);

        $response = $this->auth()->postJson('/api/v1/package-builder/build', [
            'lead_id' => $lead->id,
            'destination' => 'Dubai',
            'travel_date' => now()->addMonths(6)->toDateString(),
            'group_size' => 1,
            'components' => [
                ['type' => 'transport', 'reference_id' => $transport->id, 'quantity' => 1],
            ],
            'create_quotation' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.quotation.lead_id', $lead->id)
            ->assertJsonPath('data.quotation.status', 'draft')
            ->assertJsonCount(1, 'data.quotation.items');
        $this->assertEquals(50.0, (float) $response->json('data.quotation.total_amount'));
    }
}
