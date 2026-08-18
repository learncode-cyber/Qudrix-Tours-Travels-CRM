<?php

namespace Tests\Api;

use Tests\TestCase;
use App\Models\Webhook;
use App\Models\User;
use App\Services\Webhook\WebhookBatchingService;
use App\Services\Webhook\WebhookFilteringService;
use App\Services\Webhook\WebhookConditionalDeliveryService;
use App\Services\Webhook\WebhookAnalyticsService;
use App\Services\Webhook\WebhookPayloadTransformationService;

class WebhookAdvancedFeaturesTest extends TestCase
{
    private WebhookBatchingService $batchingService;
    private WebhookFilteringService $filteringService;
    private WebhookConditionalDeliveryService $conditionalService;
    private WebhookAnalyticsService $analyticsService;
    private WebhookPayloadTransformationService $transformationService;
    private User $user;
    private Webhook $webhook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batchingService = app(WebhookBatchingService::class);
        $this->filteringService = app(WebhookFilteringService::class);
        $this->conditionalService = app(WebhookConditionalDeliveryService::class);
        $this->analyticsService = app(WebhookAnalyticsService::class);
        $this->transformationService = app(WebhookPayloadTransformationService::class);

        $this->user = User::factory()->create(['role' => 'super-admin']);
        $this->webhook = Webhook::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test webhook batching - create batch
     */
    public function test_webhook_batching_create_batch(): void
    {
        $events = [
            ['type' => 'booking.created', 'payload' => ['booking_id' => 1]],
            ['type' => 'booking.created', 'payload' => ['booking_id' => 2]],
            ['type' => 'booking.updated', 'payload' => ['booking_id' => 3]],
        ];

        $batches = $this->batchingService->createBatch($this->webhook, $events, 2);

        $this->assertCount(2, $batches);
        $this->assertCount(2, $batches[0]);
        $this->assertCount(1, $batches[1]);
    }

    /**
     * Test webhook filtering - apply filters
     */
    public function test_webhook_filtering_apply_filters(): void
    {
        $this->webhook->update([
            'filters' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'confirmed'],
                ['field' => 'amount', 'operator' => 'greater_than', 'value' => 100],
            ]
        ]);

        $eventData = [
            'event_type' => 'booking.created',
            'status' => 'confirmed',
            'amount' => 150,
        ];

        $shouldDeliver = $this->filteringService->applyFilters($this->webhook, $eventData);
        $this->assertTrue($shouldDeliver);

        // Test with failing filter
        $eventData['amount'] = 50;
        $shouldDeliver = $this->filteringService->applyFilters($this->webhook, $eventData);
        $this->assertFalse($shouldDeliver);
    }

    /**
     * Test webhook filtering - validate filters
     */
    public function test_webhook_filtering_validate_filters(): void
    {
        $validFilters = [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'confirmed'],
        ];

        $errors = $this->filteringService->validateFilters($validFilters);
        $this->assertEmpty($errors);

        // Test invalid operator
        $invalidFilters = [
            ['field' => 'status', 'operator' => 'invalid_op', 'value' => 'confirmed'],
        ];

        $errors = $this->filteringService->validateFilters($invalidFilters);
        $this->assertNotEmpty($errors);
    }

    /**
     * Test conditional delivery - should deliver
     */
    public function test_conditional_delivery_should_deliver(): void
    {
        $this->webhook->update([
            'rate_limit' => ['window' => 60, 'max_per_window' => 100],
            'delivery_window' => ['start_time' => '08:00', 'end_time' => '20:00'],
        ]);

        $eventData = [
            'event_type' => 'booking.created',
            'status' => 'confirmed',
        ];

        $shouldDeliver = $this->conditionalService->shouldDeliver($this->webhook, $eventData);
        $this->assertTrue($shouldDeliver);
    }

    /**
     * Test conditional delivery - inactive webhook
     */
    public function test_conditional_delivery_inactive_webhook(): void
    {
        $this->webhook->update(['is_active' => false]);

        $eventData = [
            'event_type' => 'booking.created',
        ];

        $shouldDeliver = $this->conditionalService->shouldDeliver($this->webhook, $eventData);
        $this->assertFalse($shouldDeliver);
    }

    /**
     * Test payload transformation - field mapping
     */
    public function test_payload_transformation_field_mapping(): void
    {
        $this->webhook->update([
            'payload_transformations' => [
                [
                    'type' => 'field_mapping',
                    'mappings' => [
                        'booking_id' => 'id',
                        'customer.name' => 'customer_name',
                    ]
                ]
            ]
        ]);

        $payload = [
            'booking_id' => 123,
            'customer' => ['name' => 'John Doe'],
        ];

        $transformed = $this->transformationService->transformPayload($this->webhook, $payload);

        $this->assertArrayHasKey('id', $transformed);
        $this->assertArrayHasKey('customer_name', $transformed);
        $this->assertEquals(123, $transformed['id']);
        $this->assertEquals('John Doe', $transformed['customer_name']);
    }

    /**
     * Test payload transformation - field extraction
     */
    public function test_payload_transformation_field_extraction(): void
    {
        $this->webhook->update([
            'payload_transformations' => [
                [
                    'type' => 'field_extraction',
                    'fields' => ['booking_id', 'status']
                ]
            ]
        ]);

        $payload = [
            'booking_id' => 123,
            'status' => 'confirmed',
            'extra_field' => 'should_be_removed',
        ];

        $transformed = $this->transformationService->transformPayload($this->webhook, $payload);

        $this->assertArrayHasKey('booking_id', $transformed);
        $this->assertArrayHasKey('status', $transformed);
        $this->assertArrayNotHasKey('extra_field', $transformed);
    }

    /**
     * Test payload transformation - field deletion
     */
    public function test_payload_transformation_field_deletion(): void
    {
        $this->webhook->update([
            'payload_transformations' => [
                [
                    'type' => 'field_deletion',
                    'fields' => ['sensitive_data', 'password']
                ]
            ]
        ]);

        $payload = [
            'booking_id' => 123,
            'sensitive_data' => 'secret',
            'password' => 'should_be_deleted',
        ];

        $transformed = $this->transformationService->transformPayload($this->webhook, $payload);

        $this->assertArrayNotHasKey('sensitive_data', $transformed);
        $this->assertArrayNotHasKey('password', $transformed);
        $this->assertArrayHasKey('booking_id', $transformed);
    }

    /**
     * Test analytics - get analytics
     */
    public function test_analytics_get_analytics(): void
    {
        // Create some deliveries
        $this->webhook->deliveries()->create([
            'event_type' => 'booking.created',
            'payload' => json_encode(['id' => 1]),
            'status' => 'delivered',
            'retry_count' => 0,
        ]);

        $this->webhook->deliveries()->create([
            'event_type' => 'booking.updated',
            'payload' => json_encode(['id' => 2]),
            'status' => 'failed',
            'retry_count' => 2,
        ]);

        $analytics = $this->analyticsService->getAnalytics($this->webhook, '7d');

        $this->assertArrayHasKey('summary', $analytics);
        $this->assertArrayHasKey('daily_stats', $analytics);
        $this->assertArrayHasKey('event_breakdown', $analytics);
        $this->assertEquals(2, $analytics['summary']['total_deliveries']);
        $this->assertEquals(1, $analytics['summary']['delivered']);
        $this->assertEquals(1, $analytics['summary']['failed']);
    }

    /**
     * Test analytics - get event breakdown
     */
    public function test_analytics_event_breakdown(): void
    {
        $this->webhook->deliveries()->create([
            'event_type' => 'booking.created',
            'payload' => json_encode(['id' => 1]),
            'status' => 'delivered',
        ]);

        $this->webhook->deliveries()->create([
            'event_type' => 'booking.created',
            'payload' => json_encode(['id' => 2]),
            'status' => 'delivered',
        ]);

        $this->webhook->deliveries()->create([
            'event_type' => 'booking.updated',
            'payload' => json_encode(['id' => 3]),
            'status' => 'failed',
        ]);

        $analytics = $this->analyticsService->getAnalytics($this->webhook, '7d');
        
        $this->assertCount(2, $analytics['event_breakdown']);
    }

    /**
     * Test analytics - success rate
     */
    public function test_analytics_success_rate(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->webhook->deliveries()->create([
                'event_type' => 'booking.created',
                'payload' => json_encode(['id' => $i]),
                'status' => 'delivered',
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            $this->webhook->deliveries()->create([
                'event_type' => 'booking.created',
                'payload' => json_encode(['id' => $i + 8]),
                'status' => 'failed',
            ]);
        }

        $analytics = $this->analyticsService->getAnalytics($this->webhook, '7d');

        $this->assertEquals(10, $analytics['summary']['total_deliveries']);
        $this->assertEquals(80, $analytics['summary']['success_rate']);
    }

    /**
     * Test transformation validation
     */
    public function test_transformation_validation(): void
    {
        $validTransformation = [
            'type' => 'field_mapping',
            'mappings' => ['id' => 'booking_id'],
        ];

        $errors = $this->transformationService->validateTransformation($validTransformation);
        $this->assertEmpty($errors);

        // Test invalid transformation
        $invalidTransformation = [
            'type' => 'field_mapping',
            // Missing required 'mappings'
        ];

        $errors = $this->transformationService->validateTransformation($invalidTransformation);
        $this->assertNotEmpty($errors);
    }
}
