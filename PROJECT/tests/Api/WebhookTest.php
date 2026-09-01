<?php

namespace Tests\Api;

use Tests\TestCase;
use App\Models\ApiKey;
use App\Models\Webhook;
use App\Services\Webhook\HmacSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiKey = ApiKey::create([
            'name' => 'Test Webhook API',
            'key' => 'ak_testkey' . substr(md5(rand()), 0, 24),
            'secret' => hash('sha256', 'sk_testsecret'),
            'permissions' => ['webhooks:manage'],
            'expires_at' => now()->addDays(30),
            'is_revoked' => false,
        ]);
    }

    public function test_create_webhook_success()
    {
        $response = $this->postJson('/admin/api/webhooks', [
            'api_key_id' => $this->apiKey->id,
            'url' => 'https://example.com/webhook',
            'events' => ['booking.created', 'booking.updated'],
            'is_active' => true,
            'retry_limit' => 5,
        ], ['Authorization' => 'Bearer ' . auth()->guard('api')->fromUser($this->apiKey)]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'url' => 'https://example.com/webhook',
                    'is_active' => true,
                ]
            ]);
    }

    public function test_create_webhook_invalid_events()
    {
        $response = $this->postJson('/admin/api/webhooks', [
            'api_key_id' => $this->apiKey->id,
            'url' => 'https://example.com/webhook',
            'events' => ['invalid.event'],
            'is_active' => true,
        ]);

        $response->assertStatus(400);
    }

    public function test_create_webhook_invalid_url()
    {
        $response = $this->postJson('/admin/api/webhooks', [
            'api_key_id' => $this->apiKey->id,
            'url' => 'not-a-url',
            'events' => ['booking.created'],
            'is_active' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_list_webhooks()
    {
        Webhook::create([
            'api_key_id' => $this->apiKey->id,
            'url' => 'https://example.com/webhook1',
            'events' => ['booking.created'],
            'is_active' => true,
            'secret' => 'secret1',
            'retry_limit' => 5,
        ]);

        $response = $this->getJson('/admin/api/webhooks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination',
            ]);
    }

    public function test_get_webhook_details()
    {
        $webhook = Webhook::create([
            'api_key_id' => $this->apiKey->id,
            'url' => 'https://example.com/webhook',
            'events' => ['booking.created'],
            'is_active' => true,
            'secret' => 'secret',
            'retry_limit' => 5,
        ]);

        $response = $this->getJson("/admin/api/webhooks/{$webhook->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $webhook->id,
                    'url' => 'https://example.com/webhook',
                ]
            ]);
    }

    public function test_update_webhook()
    {
        $webhook = Webhook::create([
            'api_key_id' => $this->apiKey->id,
            'url' => 'https://example.com/webhook',
            'events' => ['booking.created'],
            'is_active' => true,
            'secret' => 'secret',
            'retry_limit' => 5,
        ]);

        $response = $this->putJson("/admin/api/webhooks/{$webhook->id}", [
            'url' => 'https://example.com/webhook-updated',
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'url' => 'https://example.com/webhook-updated',
                    'is_active' => false,
                ]
            ]);
    }

    public function test_delete_webhook()
    {
        $webhook = Webhook::create([
            'api_key_id' => $this->apiKey->id,
            'url' => 'https://example.com/webhook',
            'events' => ['booking.created'],
            'is_active' => true,
            'secret' => 'secret',
            'retry_limit' => 5,
        ]);

        $response = $this->deleteJson("/admin/api/webhooks/{$webhook->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_rotate_webhook_secret()
    {
        $webhook = Webhook::create([
            'api_key_id' => $this->apiKey->id,
            'url' => 'https://example.com/webhook',
            'events' => ['booking.created'],
            'is_active' => true,
            'secret' => 'original_secret',
            'retry_limit' => 5,
        ]);

        $oldSecret = $webhook->secret;

        $response = $this->postJson("/admin/api/webhooks/{$webhook->id}/rotate-secret");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['secret']]);

        $webhook->refresh();
        $this->assertNotEquals($oldSecret, $webhook->secret);
    }

    public function test_toggle_webhook()
    {
        $webhook = Webhook::create([
            'api_key_id' => $this->apiKey->id,
            'url' => 'https://example.com/webhook',
            'events' => ['booking.created'],
            'is_active' => true,
            'secret' => 'secret',
            'retry_limit' => 5,
        ]);

        $response = $this->postJson("/admin/api/webhooks/{$webhook->id}/toggle");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['is_active' => false],
            ]);
    }

    public function test_webhook_statistics()
    {
        $webhook = Webhook::create([
            'api_key_id' => $this->apiKey->id,
            'url' => 'https://example.com/webhook',
            'events' => ['booking.created'],
            'is_active' => true,
            'secret' => 'secret',
            'retry_limit' => 5,
        ]);

        $response = $this->getJson("/admin/api/webhooks/{$webhook->id}/statistics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_deliveries',
                    'successful',
                    'failed',
                    'pending',
                    'success_rate',
                ]
            ]);
    }

    public function test_hmac_signature_generation()
    {
        $service = new HmacSignatureService();
        $payload = ['event' => 'booking.created', 'data' => []];
        $secret = 'test_secret';

        $signature = $service->generateSignature($payload, $secret);

        $this->assertTrue($service->verifySignature($payload, $signature, $secret));
    }

    public function test_hmac_signature_verification_fails_with_wrong_secret()
    {
        $service = new HmacSignatureService();
        $payload = ['event' => 'booking.created', 'data' => []];
        $secret = 'test_secret';
        $wrongSecret = 'wrong_secret';

        $signature = $service->generateSignature($payload, $secret);

        $this->assertFalse($service->verifySignature($payload, $signature, $wrongSecret));
    }

    public function test_get_available_events()
    {
        $response = $this->getJson('/admin/api/webhooks/events');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'events',
            ])
            ->assertJson([
                'success' => true,
                'events' => [
                    'lead.created',
                    'lead.updated',
                    'booking.created',
                    'booking.updated',
                    'booking.confirmed',
                    'booking.cancelled',
                    'payment.updated',
                    'package.updated',
                ]
            ]);
    }
}
