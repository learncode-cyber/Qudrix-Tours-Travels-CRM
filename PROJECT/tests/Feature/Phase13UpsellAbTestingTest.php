<?php

namespace Tests\Feature;

use App\Models\AbAssignment;
use App\Models\AbExperiment;
use App\Models\AbVariant;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\FlightBooking;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\Transport;
use App\Models\UpsellRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase13UpsellAbTestingTest extends TestCase
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
            'name' => 'Upsell Tenant',
            'slug' => 'upsell-tenant',
            'is_active' => true,
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-p13',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Upsell User',
            'email' => 'upsell@example.com',
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

    private function makeBooking(array $overrides = []): Booking
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Upsell Customer', 'customer_type' => 'individual']);
        $package = Package::create(['tenant_id' => $this->tenant->id, 'name' => 'Test Package']);

        return Booking::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
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

    // --- Upsell rules CRUD ---

    public function test_upsell_rule_crud_lifecycle_and_validation()
    {
        $create = $this->auth()->postJson('/api/v1/upsell-rules', [
            'name' => 'Add Hotel',
            'trigger_type' => 'flight',
            'recommend_type' => 'hotel',
            'suggested_price' => 200,
        ]);
        $create->assertStatus(201)->assertJsonPath('data.is_active', true);
        $id = $create->json('data.id');

        $this->auth()->getJson('/api/v1/upsell-rules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('trigger_types.0', 'flight');

        $this->auth()->putJson("/api/v1/upsell-rules/{$id}", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->auth()->deleteJson("/api/v1/upsell-rules/{$id}")->assertOk();
        $this->auth()->getJson('/api/v1/upsell-rules')->assertOk()->assertJsonCount(0, 'data');

        $invalid = $this->auth()->postJson('/api/v1/upsell-rules', [
            'name' => 'x', 'trigger_type' => 'made_up', 'recommend_type' => 'hotel',
        ]);
        $invalid->assertStatus(422);
    }

    // --- forBooking: real trigger detection + availability filtering ---

    public function test_recommendations_are_not_shown_for_a_component_the_booking_already_has()
    {
        $booking = $this->makeBooking(['booking_type' => 'flight']);
        UpsellRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Add Flight', 'trigger_type' => 'any',
            'recommend_type' => 'flight', 'is_active' => true,
        ]);

        $response = $this->auth()->getJson("/api/v1/bookings/{$booking->id}/upsell-recommendations");

        $response->assertOk()->assertJsonCount(0, 'data.recommendations');
    }

    public function test_recommendation_is_skipped_when_availability_check_fails()
    {
        $booking = $this->makeBooking();
        UpsellRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Add Hotel', 'trigger_type' => 'any',
            'recommend_type' => 'hotel', 'requires_availability_check' => true, 'is_active' => true,
        ]);
        // No active hotels exist for this tenant — must be filtered out.

        $response = $this->auth()->getJson("/api/v1/bookings/{$booking->id}/upsell-recommendations");

        $response->assertOk()->assertJsonCount(0, 'data.recommendations');
    }

    public function test_recommendation_shown_with_real_availability_count()
    {
        $booking = $this->makeBooking();
        Transport::create([
            'tenant_id' => $this->tenant->id, 'transport_type' => 'van', 'vehicle_name' => 'Van',
            'vehicle_number' => 'V-1', 'pickup_location' => 'A', 'dropoff_location' => 'B',
            'pickup_date' => now()->addDays(1), 'pickup_time' => '09:00:00', 'capacity' => 8,
            'price_per_seat' => 50, 'currency' => 'USD', 'driver_name' => 'Ali', 'driver_phone' => 'x',
        ]);
        UpsellRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Add Transport', 'trigger_type' => 'any',
            'recommend_type' => 'transport', 'suggested_price' => 50, 'requires_availability_check' => true,
            'is_active' => true,
        ]);

        $response = $this->auth()->getJson("/api/v1/bookings/{$booking->id}/upsell-recommendations");

        $response->assertOk()
            ->assertJsonCount(1, 'data.recommendations')
            ->assertJsonPath('data.recommendations.0.availability.available', true)
            ->assertJsonPath('data.recommendations.0.availability.count', 1);
    }

    public function test_non_inventory_recommendation_carries_an_honest_note()
    {
        $booking = $this->makeBooking();
        UpsellRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Add Insurance', 'trigger_type' => 'any',
            'recommend_type' => 'insurance', 'is_active' => true,
        ]);

        $response = $this->auth()->getJson("/api/v1/bookings/{$booking->id}/upsell-recommendations");

        $response->assertOk()
            ->assertJsonPath('data.recommendations.0.availability.available', true)
            ->assertJsonPath('data.recommendations.0.availability.note', fn ($n) => str_contains($n, 'not tracked as inventory'));
    }

    public function test_flight_component_is_detected_from_real_join_table()
    {
        $booking = $this->makeBooking();
        // No actual Flight row needed for FlightBooking's own FK integrity in this schema check —
        // this test targets triggersFor()'s exists() check on booking_id alone via a raw insert.
        $traveler = \App\Models\BookingTraveler::create([
            'booking_id' => $booking->id,
            'first_name' => 'Test',
            'last_name' => 'Traveler',
            'email' => 'traveler@example.com',
            'phone' => '+15550001111',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'nationality' => 'US',
            'traveler_type' => 'adult',
            'emergency_contact' => 'Contact',
            'emergency_phone' => '+15550002222',
            'passport_number' => 'P1234567',
            'passport_expiry' => now()->addYears(5)->toDateString(),
        ]);
        $flight = \App\Models\Flight::create([
            'tenant_id' => $this->tenant->id,
            'airline_code' => 'EK',
            'flight_number' => 'EK202',
            'departure_airport' => 'DXB',
            'arrival_airport' => 'JFK',
            'departure_date' => now()->addMonths(3),
            'arrival_date' => now()->addMonths(3),
            'departure_time' => '08:00:00',
            'arrival_time' => '14:00:00',
            'aircraft_type' => 'A380',
            'total_seats' => 200,
            'available_seats' => 200,
            'price_per_seat' => 900,
            'currency' => 'USD',
            'status' => 'active',
        ]);
        \DB::table('flight_bookings')->insert([
            'booking_id' => $booking->id,
            'flight_id' => $flight->id,
            'booking_traveler_id' => $traveler->id,
            'seat_number' => '1A',
            'status' => 'confirmed',
        ]);

        UpsellRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Add Hotel via flight', 'trigger_type' => 'flight',
            'recommend_type' => 'hotel', 'is_active' => true,
        ]);

        $response = $this->auth()->getJson("/api/v1/bookings/{$booking->id}/upsell-recommendations");

        $response->assertOk()->assertJsonPath('data.detected_components', fn ($c) => in_array('flight', $c, true));
    }

    // --- Record shown / outcome / effectiveness ---

    public function test_record_shown_and_outcome_and_effectiveness()
    {
        $rule = UpsellRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Add Transport', 'trigger_type' => 'any',
            'recommend_type' => 'transport', 'is_active' => true,
        ]);
        $booking = $this->makeBooking();

        $shown = $this->auth()->postJson('/api/v1/upsell-recommendations', [
            'rule_id' => $rule->id, 'recommend_type' => 'transport', 'booking_id' => $booking->id,
        ]);
        $shown->assertStatus(201)->assertJsonPath('data.outcome', 'shown');
        $id = $shown->json('data.id');

        $outcome = $this->auth()->putJson("/api/v1/upsell-recommendations/{$id}/outcome", [
            'outcome' => 'accepted', 'accepted_value' => 75,
        ]);
        $outcome->assertOk()->assertJsonPath('data.outcome', 'accepted');

        $effectiveness = $this->auth()->getJson('/api/v1/upsell-effectiveness');
        $effectiveness->assertOk()
            ->assertJsonPath('data.0.shown', 1)
            ->assertJsonPath('data.0.accepted', 1);
        $this->assertEquals(100.0, (float) $effectiveness->json('data.0.acceptance_rate_percent'));
    }

    public function test_upsell_rules_are_tenant_scoped()
    {
        $mine = UpsellRule::create(['tenant_id' => $this->tenant->id, 'name' => 'Mine', 'trigger_type' => 'any', 'recommend_type' => 'hotel', 'is_active' => true]);
        UpsellRule::create(['tenant_id' => $this->otherTenant->id, 'name' => 'Not Mine', 'trigger_type' => 'any', 'recommend_type' => 'hotel', 'is_active' => true]);

        $response = $this->auth()->getJson('/api/v1/upsell-rules');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($mine->id, $response->json('data.0.id'));
    }

    // --- A/B testing ---

    private function makeLead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'tenant_id' => $this->tenant->id, 'name' => 'AB Lead', 'source' => 'website',
            'status' => 'new', 'priority' => 'medium',
        ], $overrides));
    }

    public function test_experiment_cannot_start_with_fewer_than_two_variants()
    {
        $experiment = AbExperiment::create(['tenant_id' => $this->tenant->id, 'name' => 'x', 'status' => 'draft']);
        AbVariant::create(['ab_experiment_id' => $experiment->id, 'label' => 'A', 'content' => 'x', 'is_active' => true]);

        $response = $this->auth()->postJson("/api/v1/ab-experiments/{$experiment->id}/start");

        $response->assertStatus(422)->assertJsonPath('error', 'Add at least two active variants before starting.');
        $this->assertEquals('draft', $experiment->fresh()->status);
    }

    public function test_experiment_starts_with_two_variants_and_variant_upsert_is_idempotent_by_label()
    {
        $experiment = AbExperiment::create(['tenant_id' => $this->tenant->id, 'name' => 'x', 'status' => 'draft']);

        $this->auth()->postJson("/api/v1/ab-experiments/{$experiment->id}/variants", ['label' => 'A', 'content' => 'v1'])->assertStatus(201);
        $this->auth()->postJson("/api/v1/ab-experiments/{$experiment->id}/variants", ['label' => 'A', 'content' => 'v2'])->assertStatus(201);
        $this->auth()->postJson("/api/v1/ab-experiments/{$experiment->id}/variants", ['label' => 'B', 'content' => 'x'])->assertStatus(201);

        $this->assertEquals(2, AbVariant::where('ab_experiment_id', $experiment->id)->count());
        $this->assertEquals('v2', AbVariant::where('ab_experiment_id', $experiment->id)->where('label', 'A')->first()->content);

        $start = $this->auth()->postJson("/api/v1/ab-experiments/{$experiment->id}/start");
        $start->assertOk()->assertJsonPath('data.status', 'running');
    }

    public function test_assign_is_refused_when_experiment_is_not_running()
    {
        $experiment = AbExperiment::create(['tenant_id' => $this->tenant->id, 'name' => 'x', 'status' => 'draft']);
        $lead = $this->makeLead();

        $response = $this->auth()->postJson("/api/v1/ab-experiments/{$experiment->id}/assign", ['lead_id' => $lead->id]);

        $response->assertStatus(422)->assertJsonPath('error', fn ($m) => str_contains($m, 'not running'));
    }

    public function test_assign_is_deterministic_and_idempotent_for_the_same_lead()
    {
        $experiment = AbExperiment::create(['tenant_id' => $this->tenant->id, 'name' => 'x', 'status' => 'running', 'started_at' => now()]);
        AbVariant::create(['ab_experiment_id' => $experiment->id, 'label' => 'A', 'content' => 'x', 'is_active' => true]);
        AbVariant::create(['ab_experiment_id' => $experiment->id, 'label' => 'B', 'content' => 'x', 'is_active' => true]);
        $lead = $this->makeLead();

        $first = $this->auth()->postJson("/api/v1/ab-experiments/{$experiment->id}/assign", ['lead_id' => $lead->id]);
        $first->assertOk();
        $firstVariantId = $first->json('data.ab_variant_id');

        $second = $this->auth()->postJson("/api/v1/ab-experiments/{$experiment->id}/assign", ['lead_id' => $lead->id]);
        $second->assertOk()->assertJsonPath('data.ab_variant_id', $firstVariantId);
        $this->assertEquals(1, AbAssignment::where('ab_experiment_id', $experiment->id)->count());
    }

    public function test_record_response_and_conversion_update_the_real_assignment()
    {
        $experiment = AbExperiment::create(['tenant_id' => $this->tenant->id, 'name' => 'x', 'status' => 'running', 'started_at' => now()]);
        $variant = AbVariant::create(['ab_experiment_id' => $experiment->id, 'label' => 'A', 'content' => 'x', 'is_active' => true]);
        $lead = $this->makeLead();
        $assignment = AbAssignment::create([
            'tenant_id' => $this->tenant->id, 'ab_experiment_id' => $experiment->id,
            'ab_variant_id' => $variant->id, 'lead_id' => $lead->id,
        ]);

        $this->auth()->putJson("/api/v1/ab-assignments/{$assignment->id}/response")
            ->assertOk()->assertJsonPath('data.responded', true);

        $conversion = $this->auth()->putJson("/api/v1/ab-assignments/{$assignment->id}/conversion", ['booking_value' => 500]);
        $conversion->assertOk()
            ->assertJsonPath('data.converted', true)
            ->assertJsonPath('data.responded', true);
        $this->assertEquals(500.0, (float) $conversion->json('data.booking_value'));
    }

    public function test_results_declines_to_name_a_winner_below_minimum_sample()
    {
        $experiment = AbExperiment::create(['tenant_id' => $this->tenant->id, 'name' => 'x', 'status' => 'running', 'started_at' => now()]);
        $variantA = AbVariant::create(['ab_experiment_id' => $experiment->id, 'label' => 'A', 'content' => 'x', 'is_active' => true]);
        $variantB = AbVariant::create(['ab_experiment_id' => $experiment->id, 'label' => 'B', 'content' => 'x', 'is_active' => true]);

        AbAssignment::create(['tenant_id' => $this->tenant->id, 'ab_experiment_id' => $experiment->id, 'ab_variant_id' => $variantA->id, 'lead_id' => $this->makeLead()->id, 'converted' => true]);
        AbAssignment::create(['tenant_id' => $this->tenant->id, 'ab_experiment_id' => $experiment->id, 'ab_variant_id' => $variantB->id, 'lead_id' => $this->makeLead()->id, 'converted' => false]);

        $response = $this->auth()->getJson("/api/v1/ab-experiments/{$experiment->id}/results");

        $response->assertOk()
            ->assertJsonPath('data.winner.decided', false)
            ->assertJsonPath('data.winner.reason', fn ($r) => str_contains($r, 'Sample too small'));
    }

    public function test_results_names_a_winner_with_sufficient_sample_and_clear_margin()
    {
        $experiment = AbExperiment::create(['tenant_id' => $this->tenant->id, 'name' => 'x', 'status' => 'running', 'started_at' => now()]);
        $variantA = AbVariant::create(['ab_experiment_id' => $experiment->id, 'label' => 'A', 'content' => 'x', 'is_active' => true]);
        $variantB = AbVariant::create(['ab_experiment_id' => $experiment->id, 'label' => 'B', 'content' => 'x', 'is_active' => true]);

        for ($i = 0; $i < 30; $i++) {
            AbAssignment::create([
                'tenant_id' => $this->tenant->id, 'ab_experiment_id' => $experiment->id,
                'ab_variant_id' => $variantA->id, 'lead_id' => $this->makeLead()->id,
                'converted' => $i < 20, // 20/30 = 66.7%
            ]);
            AbAssignment::create([
                'tenant_id' => $this->tenant->id, 'ab_experiment_id' => $experiment->id,
                'ab_variant_id' => $variantB->id, 'lead_id' => $this->makeLead()->id,
                'converted' => $i < 5, // 5/30 = 16.7%
            ]);
        }

        $response = $this->auth()->getJson("/api/v1/ab-experiments/{$experiment->id}/results");

        $response->assertOk()
            ->assertJsonPath('data.winner.decided', true)
            ->assertJsonPath('data.winner.variant_label', 'A');
    }

    public function test_ab_experiments_are_tenant_scoped()
    {
        $mine = AbExperiment::create(['tenant_id' => $this->tenant->id, 'name' => 'Mine', 'status' => 'draft']);
        AbExperiment::create(['tenant_id' => $this->otherTenant->id, 'name' => 'Not Mine', 'status' => 'draft']);

        $response = $this->auth()->getJson('/api/v1/ab-experiments');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($mine->id, $response->json('data.0.id'));
    }
}
