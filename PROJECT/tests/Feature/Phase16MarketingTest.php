<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase16MarketingTest extends TestCase
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
            'name' => 'Marketing Tenant',
            'slug' => 'marketing-tenant',
            'is_active' => true,
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant P16',
            'slug' => 'other-tenant-p16',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Marketing User',
            'email' => 'marketing@example.com',
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

    private function makeCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Marketing Customer',
            'customer_type' => 'individual',
            'email' => 'customer1@example.com',
        ], $overrides));
    }

    private function makeBooking(?Customer $customer = null): Booking
    {
        $customer = $customer ?? $this->makeCustomer();
        $package = Package::create(['tenant_id' => $this->tenant->id, 'name' => 'Marketing Package']);

        return Booking::create([
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
        ]);
    }

    // --- Contact Lists ---

    public function test_contact_list_crud_and_adding_members()
    {
        $list = $this->auth()->postJson('/api/v1/marketing/contact-lists', [
            'name' => 'VIP Customers',
            'description' => 'High value customers',
        ]);
        $list->assertStatus(201)->assertJsonPath('data.name', 'VIP Customers');
        $listId = $list->json('data.id');

        $customer = $this->makeCustomer();
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Marketing Lead',
            'email' => 'lead1@example.com',
            'phone' => '+15551234567',
            'source' => 'website',
            'status' => 'new',
            'priority' => 'medium',
        ]);

        $add = $this->auth()->postJson("/api/v1/marketing/contact-lists/{$listId}/members", [
            'customer_ids' => [$customer->id],
            'lead_ids' => [$lead->id],
        ]);
        $add->assertOk()
            ->assertJsonPath('data.added', 2)
            ->assertJsonPath('data.total_members', 2);

        $index = $this->auth()->getJson('/api/v1/marketing/contact-lists');
        $index->assertOk()->assertJsonPath('data.0.members_count', 2);
    }

    public function test_contact_lists_are_tenant_scoped()
    {
        $this->auth()->postJson('/api/v1/marketing/contact-lists', ['name' => 'Mine'])->assertStatus(201);

        \App\Models\ContactList::create(['tenant_id' => $this->otherTenant->id, 'name' => 'Not mine']);

        $index = $this->auth()->getJson('/api/v1/marketing/contact-lists');
        $index->assertOk();
        $this->assertCount(1, $index->json('data'));
    }

    // --- Campaigns ---

    public function test_email_campaign_full_lifecycle_prepare_and_send()
    {
        Mail::fake();

        $customer = $this->makeCustomer(['email' => 'reachable@example.com']);
        $customerNoEmail = $this->makeCustomer(['email' => null, 'name' => 'No Email Customer']);

        $list = \App\Models\ContactList::create(['tenant_id' => $this->tenant->id, 'name' => 'Email List']);
        \App\Models\ContactListMember::create(['contact_list_id' => $list->id, 'customer_id' => $customer->id]);
        \App\Models\ContactListMember::create(['contact_list_id' => $list->id, 'customer_id' => $customerNoEmail->id]);

        $campaign = $this->auth()->postJson('/api/v1/marketing/campaigns', [
            'name' => 'Spring Sale',
            'channel' => 'email',
            'contact_list_id' => $list->id,
            'subject' => 'Big discounts',
            'body' => 'Check out our spring sale!',
        ]);
        $campaign->assertStatus(201)->assertJsonPath('data.status', 'draft');
        $id = $campaign->json('data.id');

        $prepare = $this->auth()->postJson("/api/v1/marketing/campaigns/{$id}/prepare");
        $prepare->assertOk()->assertJsonPath('data.recipients_processed', 2);

        // send() only loops recipients still 'pending' — the no-destination
        // customer was already marked 'skipped' at prepare() time, so it
        // isn't part of *this* send() call's own tally (only the pending,
        // deliverable recipient is). The full picture (both skips) shows up
        // in the campaign's own stats/report, asserted below.
        $send = $this->auth()->postJson("/api/v1/marketing/campaigns/{$id}/send");
        $send->assertOk()
            ->assertJsonPath('data.result.sent', 1)
            ->assertJsonPath('data.result.skipped', 0);

        $report = $this->auth()->getJson("/api/v1/marketing/campaigns/{$id}/report");
        $report->assertOk()
            ->assertJsonPath('data.stats.sent', 1)
            ->assertJsonPath('data.stats.skipped', 1);
        $this->assertCount(1, $report->json('data.failures'));
        $this->assertSame('skipped', $report->json('data.failures.0.status'));
        $this->assertStringContainsString('No email destination', $report->json('data.failures.0.failure_reason'));
    }

    public function test_sms_campaign_honestly_skips_every_recipient_with_no_configured_provider()
    {
        $customer = $this->makeCustomer(['phone' => '+15559998888']);
        $list = \App\Models\ContactList::create(['tenant_id' => $this->tenant->id, 'name' => 'SMS List']);
        \App\Models\ContactListMember::create(['contact_list_id' => $list->id, 'customer_id' => $customer->id]);

        $campaign = $this->auth()->postJson('/api/v1/marketing/campaigns', [
            'name' => 'SMS Blast',
            'channel' => 'sms',
            'contact_list_id' => $list->id,
            'body' => 'Hello via SMS',
        ]);
        $id = $campaign->json('data.id');

        $this->auth()->postJson("/api/v1/marketing/campaigns/{$id}/prepare")->assertOk();
        $send = $this->auth()->postJson("/api/v1/marketing/campaigns/{$id}/send");

        $send->assertOk()
            ->assertJsonPath('data.result.sent', 0)
            ->assertJsonPath('data.result.skipped', 1);

        $campaign = \App\Models\Campaign::find($id);
        $this->assertSame('failed', $campaign->status);
        $recipient = $campaign->recipients()->first();
        $this->assertStringContainsString('CONTRACT REQUIRED', $recipient->failure_reason);
    }

    public function test_campaign_cannot_be_prepared_without_a_contact_list()
    {
        $campaign = $this->auth()->postJson('/api/v1/marketing/campaigns', [
            'name' => 'No List Campaign',
            'channel' => 'email',
            'body' => 'Body',
        ]);
        $id = $campaign->json('data.id');

        $this->auth()->postJson("/api/v1/marketing/campaigns/{$id}/prepare")->assertStatus(422);
    }

    public function test_campaign_cannot_be_sent_twice()
    {
        Mail::fake();
        $customer = $this->makeCustomer(['email' => 'twice@example.com']);
        $list = \App\Models\ContactList::create(['tenant_id' => $this->tenant->id, 'name' => 'Twice List']);
        \App\Models\ContactListMember::create(['contact_list_id' => $list->id, 'customer_id' => $customer->id]);

        $campaign = $this->auth()->postJson('/api/v1/marketing/campaigns', [
            'name' => 'Once Campaign',
            'channel' => 'email',
            'contact_list_id' => $list->id,
            'body' => 'Body',
        ]);
        $id = $campaign->json('data.id');

        $this->auth()->postJson("/api/v1/marketing/campaigns/{$id}/prepare")->assertOk();
        $this->auth()->postJson("/api/v1/marketing/campaigns/{$id}/send")->assertOk();

        $this->auth()->postJson("/api/v1/marketing/campaigns/{$id}/send")->assertStatus(422);
    }

    public function test_campaigns_are_tenant_scoped()
    {
        $other = \App\Models\Campaign::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Campaign',
            'channel' => 'email',
            'body' => 'x',
            'status' => 'draft',
        ]);

        $this->auth()->getJson("/api/v1/marketing/campaigns/{$other->id}")->assertStatus(404);
    }

    // --- Coupons ---

    public function test_coupon_crud_and_validate_without_redeeming()
    {
        $coupon = $this->auth()->postJson('/api/v1/marketing/coupons', [
            'code' => 'SAVE20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);
        $coupon->assertStatus(201)->assertJsonPath('data.code', 'SAVE20');

        $validate = $this->auth()->postJson('/api/v1/marketing/coupons/validate', [
            'code' => 'SAVE20',
            'booking_amount' => 500,
        ]);
        $validate->assertOk()
            ->assertJsonPath('data.valid', true);
        $this->assertEquals(100.0, (float) $validate->json('data.discount'));

        // Validating does not redeem — used_count stays 0.
        $this->assertSame(0, Coupon::where('code', 'SAVE20')->first()->used_count);
    }

    public function test_percentage_discount_over_100_is_rejected_at_creation()
    {
        $this->auth()->postJson('/api/v1/marketing/coupons', [
            'code' => 'TOOBIG',
            'discount_type' => 'percentage',
            'discount_value' => 150,
        ])->assertStatus(422);
    }

    public function test_coupon_redemption_is_real_transactional_and_cannot_double_redeem()
    {
        Coupon::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'FLAT50',
            'discount_type' => 'fixed',
            'discount_value' => 50,
            'is_active' => true,
            'used_count' => 0,
        ]);
        $booking = $this->makeBooking();

        $redeem = $this->auth()->postJson('/api/v1/marketing/coupons/redeem', [
            'code' => 'FLAT50',
            'booking_id' => $booking->id,
        ]);
        $redeem->assertStatus(201)
            ->assertJsonPath('data.discount_applied', 50)
            ->assertJsonPath('data.booking_amount_before', 1000);
        $this->assertEquals(950.0, (float) $redeem->json('data.booking_amount_after'));

        $this->assertSame(1, Coupon::where('code', 'FLAT50')->first()->used_count);

        // Applying the same coupon to the same booking again is rejected.
        $this->auth()->postJson('/api/v1/marketing/coupons/redeem', [
            'code' => 'FLAT50',
            'booking_id' => $booking->id,
        ])->assertStatus(422);
    }

    public function test_coupon_below_minimum_booking_amount_is_rejected()
    {
        Coupon::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'MIN500',
            'discount_type' => 'fixed',
            'discount_value' => 50,
            'min_booking_amount' => 5000,
            'is_active' => true,
            'used_count' => 0,
        ]);
        $booking = $this->makeBooking();

        $redeem = $this->auth()->postJson('/api/v1/marketing/coupons/redeem', [
            'code' => 'MIN500',
            'booking_id' => $booking->id,
        ]);
        $redeem->assertStatus(422);
    }

    public function test_expired_coupon_is_rejected()
    {
        Coupon::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'EXPIRED',
            'discount_type' => 'fixed',
            'discount_value' => 10,
            'valid_until' => now()->subDay()->toDateString(),
            'is_active' => true,
            'used_count' => 0,
        ]);

        $validate = $this->auth()->postJson('/api/v1/marketing/coupons/validate', [
            'code' => 'EXPIRED',
            'booking_amount' => 100,
        ]);
        $validate->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.reason', 'This coupon has expired.');
    }

    public function test_coupons_are_tenant_scoped()
    {
        Coupon::create([
            'tenant_id' => $this->otherTenant->id,
            'code' => 'OTHERTEN',
            'discount_type' => 'fixed',
            'discount_value' => 10,
            'is_active' => true,
            'used_count' => 0,
        ]);

        $validate = $this->auth()->postJson('/api/v1/marketing/coupons/validate', [
            'code' => 'OTHERTEN',
            'booking_amount' => 100,
        ]);
        $validate->assertStatus(404);
    }
}
