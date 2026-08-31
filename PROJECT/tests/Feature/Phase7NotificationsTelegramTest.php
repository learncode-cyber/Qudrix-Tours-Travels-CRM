<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class Phase7NotificationsTelegramTest extends TestCase
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
            'name' => 'Notif Tenant',
            'slug' => 'notif-tenant',
            'is_active' => true,
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-p7',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Notif User',
            'email' => 'notif@example.com',
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

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Conv Customer',
            'email' => 'convcust@example.com',
            'phone' => '+15550000000',
            'customer_type' => 'individual',
        ]);
    }

    // --- Notifications ---

    public function test_notification_lifecycle()
    {
        Notification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'lead_assigned',
            'title' => 'New lead',
            'message' => 'A lead was assigned to you.',
        ]);

        $this->auth()->getJson('/api/v1/notifications/unread-count')
            ->assertOk()->assertJsonPath('data.unread_count', 1);

        $list = $this->auth()->getJson('/api/v1/notifications');
        $list->assertOk()->assertJsonCount(1, 'data');
        $id = $list->json('data.0.id');

        $this->auth()->putJson("/api/v1/notifications/{$id}/read")
            ->assertOk()->assertJsonPath('data.read_at', fn ($v) => $v !== null);

        $this->auth()->getJson('/api/v1/notifications/unread-count')
            ->assertOk()->assertJsonPath('data.unread_count', 0);
    }

    public function test_mark_all_read()
    {
        Notification::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => 'a', 'title' => 'A', 'message' => 'a']);
        Notification::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => 'b', 'title' => 'B', 'message' => 'b']);

        $this->auth()->putJson('/api/v1/notifications/read-all')->assertOk();
        $this->auth()->getJson('/api/v1/notifications/unread-count')
            ->assertOk()->assertJsonPath('data.unread_count', 0);
    }

    public function test_notifications_are_scoped_to_the_requesting_user_and_tenant()
    {
        $otherUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Other User',
            'email' => 'other-notif@example.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'status' => 'active',
        ]);
        Notification::create(['tenant_id' => $this->tenant->id, 'user_id' => $otherUser->id, 'type' => 'x', 'title' => 'X', 'message' => 'x']);
        Notification::create(['tenant_id' => $this->otherTenant->id, 'user_id' => $this->user->id, 'type' => 'y', 'title' => 'Y', 'message' => 'y']);

        $this->auth()->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_lead_assignment_triggers_a_real_notification()
    {
        $response = $this->auth()->postJson('/api/v1/leads', [
            'name' => 'Assigned Lead',
            'email' => 'assignedlead@example.com',
            'phone' => '+15559990000',
            'source' => 'website',
            'priority' => 'medium',
            'assigned_to' => $this->user->id,
        ]);
        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'lead_assigned',
        ]);
    }

    // --- Telegram profile wiring ---

    public function test_profile_update_stores_telegram_chat_id()
    {
        $response = $this->auth()->putJson('/api/v1/profile', ['telegram_chat_id' => '999888777']);
        $response->assertOk()->assertJsonPath('user.telegram_chat_id', '999888777');
        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'telegram_chat_id' => '999888777']);
    }

    // --- Conversations ---

    public function test_conversation_requires_a_customer_or_lead()
    {
        $response = $this->auth()->postJson('/api/v1/conversations', ['channel' => 'telegram']);
        $response->assertStatus(422)->assertJsonPath('error', 'A conversation must be linked to a customer or a lead.');
    }

    public function test_conversation_store_persists_external_thread_id()
    {
        $customer = $this->makeCustomer();

        $response = $this->auth()->postJson('/api/v1/conversations', [
            'customer_id' => $customer->id,
            'channel' => 'telegram',
            'external_thread_id' => '123456789',
            'subject' => 'Test',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.external_thread_id', '123456789');
    }

    public function test_reply_on_telegram_conversation_without_chat_id_is_not_attempted()
    {
        $customer = $this->makeCustomer();
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'channel' => 'telegram',
            'status' => 'open',
        ]);

        $response = $this->auth()->postJson("/api/v1/conversations/{$conversation->id}/reply", ['body' => 'Hello']);

        $response->assertStatus(201)
            ->assertJsonPath('data.delivery_status', 'not_attempted')
            ->assertJsonPath('data.delivery_error', 'No Telegram chat id stored on this conversation.');
    }

    public function test_reply_on_telegram_conversation_with_chat_id_attempts_delivery_honestly()
    {
        $customer = $this->makeCustomer();
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'channel' => 'telegram',
            'external_thread_id' => '987654321',
            'status' => 'open',
        ]);

        $response = $this->auth()->postJson("/api/v1/conversations/{$conversation->id}/reply", ['body' => 'Hello']);

        // No TELEGRAM_BOT_TOKEN configured in this test environment — the
        // service must report that honestly, never fabricate "sent: true".
        $response->assertStatus(201)
            ->assertJsonPath('data.delivery_status', 'failed')
            ->assertJsonPath('data.delivery_error', 'CONTRACT REQUIRED: TELEGRAM_BOT_TOKEN is not configured');
    }

    public function test_internal_note_is_never_attempted_for_delivery()
    {
        $customer = $this->makeCustomer();
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'channel' => 'telegram',
            'external_thread_id' => '987654321',
            'status' => 'open',
        ]);

        $response = $this->auth()->postJson("/api/v1/conversations/{$conversation->id}/reply", [
            'body' => 'Internal note',
            'is_internal_note' => true,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.delivery_status', null);
    }

    public function test_record_inbound_bumps_unread_count_and_reopens_closed_conversation()
    {
        $customer = $this->makeCustomer();
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'channel' => 'telegram',
            'status' => 'closed',
            'unread_count' => 0,
        ]);

        $response = $this->auth()->postJson("/api/v1/conversations/{$conversation->id}/inbound", [
            'body' => 'Customer message',
        ]);
        $response->assertStatus(201);

        $conversation->refresh();
        $this->assertEquals('open', $conversation->status);
        $this->assertEquals(1, $conversation->unread_count);
    }

    public function test_show_conversation_clears_unread_count()
    {
        $customer = $this->makeCustomer();
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'channel' => 'telegram',
            'status' => 'open',
            'unread_count' => 3,
        ]);

        $response = $this->auth()->getJson("/api/v1/conversations/{$conversation->id}");
        $response->assertOk()->assertJsonPath('data.unread_count', 0);
    }

    public function test_assign_and_update_status()
    {
        $customer = $this->makeCustomer();
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'channel' => 'internal',
            'status' => 'open',
        ]);

        $this->auth()->putJson("/api/v1/conversations/{$conversation->id}/assign", ['assigned_to' => $this->user->id])
            ->assertOk()->assertJsonPath('data.assigned_to', $this->user->id);

        $this->auth()->putJson("/api/v1/conversations/{$conversation->id}/status", ['status' => 'closed'])
            ->assertOk()->assertJsonPath('data.status', 'closed');
    }

    public function test_internal_channel_reply_is_never_attempted()
    {
        $customer = $this->makeCustomer();
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'channel' => 'internal',
            'status' => 'open',
        ]);

        $response = $this->auth()->postJson("/api/v1/conversations/{$conversation->id}/reply", ['body' => 'Internal only']);

        $response->assertStatus(201)->assertJsonPath('data.delivery_status', 'not_attempted');
    }

    public function test_conversations_are_tenant_scoped()
    {
        $mineCustomer = $this->makeCustomer();
        $mine = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $mineCustomer->id,
            'channel' => 'internal',
            'status' => 'open',
        ]);

        $otherCustomer = Customer::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Customer',
            'customer_type' => 'individual',
        ]);
        Conversation::create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'channel' => 'internal',
            'status' => 'open',
        ]);

        $response = $this->auth()->getJson('/api/v1/conversations');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals($mine->id, $response->json('data.0.id'));
    }
}
