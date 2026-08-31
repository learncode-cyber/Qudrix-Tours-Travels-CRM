<?php

namespace Tests\Feature;

use App\Models\HajjPackage;
use App\Models\HajjUmrahGroup;
use App\Models\Pilgrim;
use App\Models\StudentVisaApplication;
use App\Models\Tenant;
use App\Models\UmrahPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase5HajjUmrahStudentVisaTest extends TestCase
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
            'name' => 'Hajj Umrah Tenant',
            'slug' => 'hajj-umrah-tenant',
            'is_active' => true,
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-p5',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Hajj Umrah User',
            'email' => 'hajjumrah@example.com',
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

    private function makeHajjPackage(array $overrides = []): HajjPackage
    {
        return HajjPackage::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard Hajj',
            'duration_days' => 21,
            'price' => 4500,
            'currency' => 'USD',
            'max_capacity' => 40,
            'status' => 'active',
        ], $overrides));
    }

    private function makeGroup(array $overrides = []): HajjUmrahGroup
    {
        $package = $this->makeHajjPackage();

        return HajjUmrahGroup::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'package_type' => 'hajj',
            'package_id' => $package->id,
            'name' => 'Hajj Group A',
            'departure_date' => now()->addMonths(6),
            'return_date' => now()->addMonths(6)->addDays(21),
            'capacity' => 2,
            'status' => 'planned',
        ], $overrides));
    }

    // --- Hajj packages ---

    public function test_hajj_package_crud_lifecycle()
    {
        $response = $this->auth()->postJson('/api/v1/hajj', [
            'name' => 'Premium Hajj 2027',
            'duration_days' => 21,
            'price' => 6000,
            'max_capacity' => 30,
            'rituals_included' => ['tawaf', 'saee'],
            'accommodations' => ['makkah' => '5-star'],
        ]);
        $response->assertStatus(201);
        $id = $response->json('data.id');

        $this->auth()->getJson("/api/v1/hajj/{$id}")->assertOk()
            ->assertJsonPath('data.name', 'Premium Hajj 2027');

        $update = $this->auth()->putJson("/api/v1/hajj/{$id}", ['status' => 'discontinued']);
        $update->assertOk()->assertJsonPath('data.status', 'discontinued');

        $this->auth()->getJson('/api/v1/hajj')->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_hajj_packages_are_tenant_scoped()
    {
        $mine = $this->makeHajjPackage(['name' => 'Mine']);
        HajjPackage::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Not Mine',
            'duration_days' => 14,
            'price' => 3000,
            'max_capacity' => 20,
            'status' => 'active',
        ]);

        $response = $this->auth()->getJson('/api/v1/hajj');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($mine->id, $response->json('data.0.id'));
    }

    // --- Umrah packages ---

    public function test_umrah_package_create_and_list_no_update_route()
    {
        $response = $this->auth()->postJson('/api/v1/umrah', [
            'name' => 'Ramadan Umrah',
            'duration_days' => 10,
            'price' => 1800,
            'max_capacity' => 25,
            'rituals_included' => ['umrah'],
        ]);
        $response->assertStatus(201);
        $id = $response->json('data.id');

        $this->auth()->getJson("/api/v1/umrah/{$id}")->assertOk();
        $this->auth()->getJson('/api/v1/umrah')->assertOk()->assertJsonCount(1, 'data');

        // No update route registered for umrah packages.
        $this->auth()->putJson("/api/v1/umrah/{$id}", ['status' => 'sold_out'])->assertStatus(405);
    }

    // --- Hajj/Umrah groups ---

    public function test_group_create_requires_valid_package_reference()
    {
        $response = $this->auth()->postJson('/api/v1/hajj-umrah-groups', [
            'package_type' => 'hajj',
            'package_id' => 999999,
            'name' => 'Ghost Group',
            'departure_date' => now()->addMonths(3)->toDateString(),
            'return_date' => now()->addMonths(3)->addDays(10)->toDateString(),
            'capacity' => 10,
        ]);
        $response->assertStatus(404);
    }

    public function test_group_lifecycle_and_report()
    {
        $group = $this->makeGroup(['capacity' => 3]);

        $show = $this->auth()->getJson("/api/v1/hajj-umrah-groups/{$group->id}");
        $show->assertOk()
            ->assertJsonPath('data.seats_available', 3)
            ->assertJsonPath('data.package.name', 'Standard Hajj');

        $update = $this->auth()->putJson("/api/v1/hajj-umrah-groups/{$group->id}", ['status' => 'confirmed']);
        $update->assertOk()->assertJsonPath('data.status', 'confirmed');

        Pilgrim::create([
            'tenant_id' => $this->tenant->id,
            'hajj_umrah_group_id' => $group->id,
            'name' => 'Ahmed Khan',
            'status' => 'registered',
            'payment_status' => 'partial',
            'amount_due' => 4500,
            'amount_paid' => 2000,
        ]);

        $report = $this->auth()->getJson("/api/v1/hajj-umrah-groups/{$group->id}/report");
        $report->assertOk()
            ->assertJsonPath('data.total_pilgrims', 1)
            ->assertJsonPath('data.seats_available', 2)
            ->assertJsonPath('data.unassigned_rooms', 1);
        $this->assertEquals(4500, (float) $report->json('data.total_amount_due'));
        $this->assertEquals(2000, (float) $report->json('data.total_amount_paid'));
        $this->assertEquals(2500, (float) $report->json('data.total_balance'));
    }

    public function test_groups_are_tenant_scoped()
    {
        $mine = $this->makeGroup();

        $otherPackage = HajjPackage::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Hajj',
            'duration_days' => 20,
            'price' => 4000,
            'max_capacity' => 20,
            'status' => 'active',
        ]);
        HajjUmrahGroup::create([
            'tenant_id' => $this->otherTenant->id,
            'package_type' => 'hajj',
            'package_id' => $otherPackage->id,
            'name' => 'Other Group',
            'departure_date' => now()->addMonths(6),
            'return_date' => now()->addMonths(6)->addDays(21),
            'capacity' => 10,
            'status' => 'planned',
        ]);

        $response = $this->auth()->getJson('/api/v1/hajj-umrah-groups');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($mine->id, $response->json('data.0.id'));
    }

    // --- Pilgrims ---

    public function test_pilgrim_full_lifecycle()
    {
        $group = $this->makeGroup(['capacity' => 5]);

        $create = $this->auth()->postJson('/api/v1/pilgrims', [
            'hajj_umrah_group_id' => $group->id,
            'name' => 'Fatima Noor',
            'passport_number' => 'P9988776',
            'gender' => 'female',
            'amount_due' => 4500,
        ]);
        $create->assertStatus(201)
            ->assertJsonPath('data.status', 'registered')
            ->assertJsonPath('data.payment_status', 'pending');
        $id = $create->json('data.id');

        $this->auth()->putJson("/api/v1/pilgrims/{$id}", ['mahram_name' => 'Omar Noor'])
            ->assertOk()->assertJsonPath('data.mahram_name', 'Omar Noor');

        $this->auth()->putJson("/api/v1/pilgrims/{$id}/room", ['room_number' => '512'])
            ->assertOk()->assertJsonPath('data.room_number', '512');

        $this->auth()->putJson("/api/v1/pilgrims/{$id}/transport", ['transport_assignment' => 'Bus 3'])
            ->assertOk()->assertJsonPath('data.transport_assignment', 'Bus 3');

        $payment = $this->auth()->postJson("/api/v1/pilgrims/{$id}/payments", ['amount' => 4500]);
        $payment->assertOk()->assertJsonPath('data.payment_status', 'paid');
        $this->assertEquals(4500, (float) $payment->json('data.amount_paid'));

        $this->auth()->getJson('/api/v1/pilgrims?hajj_umrah_group_id=' . $group->id)
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_pilgrim_registration_rejected_when_group_full()
    {
        $group = $this->makeGroup(['capacity' => 1]);

        Pilgrim::create([
            'tenant_id' => $this->tenant->id,
            'hajj_umrah_group_id' => $group->id,
            'name' => 'First Pilgrim',
            'status' => 'registered',
            'payment_status' => 'pending',
            'amount_due' => 0,
            'amount_paid' => 0,
        ]);

        $response = $this->auth()->postJson('/api/v1/pilgrims', [
            'hajj_umrah_group_id' => $group->id,
            'name' => 'Second Pilgrim',
        ]);

        $response->assertStatus(400)->assertJsonPath('error', 'Group is at full capacity');
    }

    public function test_pilgrims_are_tenant_scoped()
    {
        $group = $this->makeGroup();
        $mine = Pilgrim::create([
            'tenant_id' => $this->tenant->id,
            'hajj_umrah_group_id' => $group->id,
            'name' => 'Mine',
            'status' => 'registered',
            'payment_status' => 'pending',
            'amount_due' => 0,
            'amount_paid' => 0,
        ]);

        $otherPackage = HajjPackage::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Hajj',
            'duration_days' => 20,
            'price' => 4000,
            'max_capacity' => 20,
            'status' => 'active',
        ]);
        $otherGroup = HajjUmrahGroup::create([
            'tenant_id' => $this->otherTenant->id,
            'package_type' => 'hajj',
            'package_id' => $otherPackage->id,
            'name' => 'Other Group',
            'departure_date' => now()->addMonths(6),
            'return_date' => now()->addMonths(6)->addDays(21),
            'capacity' => 10,
            'status' => 'planned',
        ]);
        Pilgrim::create([
            'tenant_id' => $this->otherTenant->id,
            'hajj_umrah_group_id' => $otherGroup->id,
            'name' => 'Not Mine',
            'status' => 'registered',
            'payment_status' => 'pending',
            'amount_due' => 0,
            'amount_paid' => 0,
        ]);

        $response = $this->auth()->getJson('/api/v1/pilgrims');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($mine->id, $response->json('data.0.id'));

        $this->auth()->getJson("/api/v1/pilgrims/{$otherGroup->id}")->assertStatus(404);
    }

    // --- Student visa applications ---

    public function test_student_visa_application_full_lifecycle()
    {
        $create = $this->auth()->postJson('/api/v1/student-visa-applications', [
            'student_name' => 'Layla Hussain',
            'destination_country' => 'GB',
            'university' => 'UCL',
            'course' => 'MSc CS',
            'intake' => 'Fall 2027',
        ]);
        $create->assertStatus(201)
            ->assertJsonPath('data.application_status', 'inquiry')
            ->assertJsonPath('data.visa_status', 'not_applied')
            ->assertJsonPath('data.service_fee_currency', 'USD');
        $id = $create->json('data.id');

        $this->auth()->putJson("/api/v1/student-visa-applications/{$id}", ['university' => 'Imperial College'])
            ->assertOk()->assertJsonPath('data.university', 'Imperial College');

        $this->auth()->putJson("/api/v1/student-visa-applications/{$id}/status", ['application_status' => 'documents_pending'])
            ->assertOk()->assertJsonPath('data.application_status', 'documents_pending');

        $offer = $this->auth()->postJson("/api/v1/student-visa-applications/{$id}/offer-letter", ['offer_letter_date' => '2026-09-15']);
        $offer->assertOk()
            ->assertJsonPath('data.offer_letter_received', true)
            ->assertJsonPath('data.application_status', 'offer_received');

        $appointment = $this->auth()->postJson("/api/v1/student-visa-applications/{$id}/embassy-appointment", ['embassy_appointment_date' => '2026-10-01T10:00:00']);
        $appointment->assertOk()->assertJsonPath('data.application_status', 'visa_appointment_scheduled');

        $visaStatus = $this->auth()->putJson("/api/v1/student-visa-applications/{$id}/visa-status", ['visa_status' => 'submitted']);
        $visaStatus->assertOk()
            ->assertJsonPath('data.visa_status', 'submitted')
            ->assertJsonPath('data.application_status', 'visa_submitted');

        $counsellor = $this->auth()->postJson("/api/v1/student-visa-applications/{$id}/assign-counsellor", ['assigned_counsellor_id' => $this->user->id]);
        $counsellor->assertOk()->assertJsonPath('data.assigned_counsellor_id', $this->user->id);
    }

    public function test_student_visa_destination_country_must_be_two_letters()
    {
        $response = $this->auth()->postJson('/api/v1/student-visa-applications', [
            'student_name' => 'Bad Country',
            'destination_country' => 'GBR',
        ]);
        $response->assertStatus(422);
    }

    public function test_student_visa_applications_filter_by_status_and_are_tenant_scoped()
    {
        StudentVisaApplication::create([
            'tenant_id' => $this->tenant->id,
            'student_name' => 'Inquiry Student',
            'destination_country' => 'US',
            'application_status' => 'inquiry',
            'visa_status' => 'not_applied',
            'payment_status' => 'pending',
            'service_fee_currency' => 'USD',
        ]);
        $mine = StudentVisaApplication::create([
            'tenant_id' => $this->tenant->id,
            'student_name' => 'Applied Student',
            'destination_country' => 'CA',
            'application_status' => 'applied',
            'visa_status' => 'not_applied',
            'payment_status' => 'pending',
            'service_fee_currency' => 'USD',
        ]);
        StudentVisaApplication::create([
            'tenant_id' => $this->otherTenant->id,
            'student_name' => 'Other Tenant Student',
            'destination_country' => 'US',
            'application_status' => 'applied',
            'visa_status' => 'not_applied',
            'payment_status' => 'pending',
            'service_fee_currency' => 'USD',
        ]);

        $response = $this->auth()->getJson('/api/v1/student-visa-applications?application_status=applied');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($mine->id, $response->json('data.0.id'));
    }
}
