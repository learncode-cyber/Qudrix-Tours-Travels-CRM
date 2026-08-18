<?php

namespace Tests\Api;

use Tests\TestCase;
use App\Models\ApiKey;
use App\Models\Package;
use App\Models\Booking;
use App\Models\Customer;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Public API Test Suite
 * Tests all public endpoints used by website
 */
class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    protected ApiKey $apiKey;
    protected ApiKeyService $apiKeyService;
    protected string $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiKeyService = app(ApiKeyService::class);

        // Create test API key
        $this->apiKey = $this->apiKeyService->createKey(
            name: 'Test Website Integration',
            tenantId: 1,
            permissions: ['packages:read', 'bookings:create', 'quotations:create'],
            description: 'For testing',
            expiresAt: now()->addYear(),
            createdBy: 1
        );

        // Store the raw secret for testing
        $this->apiKeySecret = $this->apiKey->secret;
    }

    // ============================================
    // PACKAGE ENDPOINTS
    // ============================================

    /**
     * @test
     * Test: GET /api/v1/packages - List packages
     */
    public function test_list_packages_success()
    {
        // Create test packages
        Package::factory()->count(15)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/packages', [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id', 'name', 'type', 'price', 'currency',
                    'duration_days', 'capacity', 'bookings_count',
                ]
            ],
            'pagination' => [
                'current_page', 'per_page', 'total', 'total_pages', 'has_more',
            ],
        ]);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('pagination.total', 15);
    }

    /**
     * @test
     * Test: GET /api/v1/packages - With search filter
     */
    public function test_list_packages_with_search()
    {
        Package::factory()->create(['name' => 'Hajj Premium 2024', 'is_active' => true]);
        Package::factory()->create(['name' => 'Umrah Standard', 'is_active' => true]);

        $response = $this->getJson('/api/v1/packages?search=Hajj', [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.total', 1);
        $response->assertJsonPath('data.0.name', 'Hajj Premium 2024');
    }

    /**
     * @test
     * Test: GET /api/v1/packages - With type filter
     */
    public function test_list_packages_with_type_filter()
    {
        Package::factory()->create(['type' => 'hajj', 'is_active' => true]);
        Package::factory()->count(3)->create(['type' => 'umrah', 'is_active' => true]);

        $response = $this->getJson('/api/v1/packages?type=hajj', [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.total', 1);
        $response->assertJsonPath('data.0.type', 'hajj');
    }

    /**
     * @test
     * Test: GET /api/v1/packages/{id} - Get single package
     */
    public function test_get_package_details()
    {
        $package = Package::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/packages/{$package->id}", [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id', 'name', 'description', 'price', 'duration_days',
                'capacity', 'available_seats', 'itinerary', 'inclusions',
                'exclusions', 'highlights', 'terms_conditions',
            ],
        ]);
        $response->assertJsonPath('data.id', $package->id);
    }

    /**
     * @test
     * Test: GET /api/v1/packages/{id} - Package not found
     */
    public function test_get_package_not_found()
    {
        $response = $this->getJson('/api/v1/packages/99999', [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', 'NOT_FOUND');
    }

    // ============================================
    // BOOKING ENDPOINTS
    // ============================================

    /**
     * @test
     * Test: POST /api/v1/bookings - Create booking
     */
    public function test_create_booking_success()
    {
        $package = Package::factory()->create(['is_active' => true, 'capacity' => 50]);

        $bookingData = [
            'package_id' => $package->id,
            'customer' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '01700000000',
                'address' => '123 Main St',
            ],
            'travelers' => [
                ['name' => 'John Doe', 'age' => 30, 'passport' => 'AB123456'],
                ['name' => 'Jane Doe', 'age' => 28, 'passport' => 'CD789012'],
            ],
            'travel_date' => now()->addMonth()->format('Y-m-d'),
            'special_requests' => 'Non-vegetarian meals',
        ];

        $response = $this->postJson('/api/v1/bookings', $bookingData, [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'id', 'booking_reference', 'status', 'payment_status',
                'total_price', 'currency', 'created_at',
            ],
        ]);

        // Verify booking was created
        $this->assertDatabaseHas('bookings', [
            'package_id' => $package->id,
            'booking_status' => 'pending',
        ]);
    }

    /**
     * @test
     * Test: POST /api/v1/bookings - Validation error
     */
    public function test_create_booking_validation_error()
    {
        $response = $this->postJson('/api/v1/bookings', [
            'package_id' => 99999,
            'customer' => [
                'name' => 'John Doe',
                'email' => 'invalid-email',
            ],
            'travelers' => [],
        ], [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', 'VALIDATION_ERROR');
        $response->assertJsonStructure(['errors']);
    }

    /**
     * @test
     * Test: GET /api/v1/bookings/{reference} - Get booking status
     */
    public function test_get_booking_status()
    {
        $booking = Booking::factory()->create();

        $response = $this->getJson("/api/v1/bookings/{$booking->booking_reference}", [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.booking_reference', $booking->booking_reference);
    }

    // ============================================
    // QUOTATION ENDPOINTS
    // ============================================

    /**
     * @test
     * Test: POST /api/v1/quotations - Request quotation
     */
    public function test_request_quotation_success()
    {
        $package = Package::factory()->create(['is_active' => true, 'price' => 450000]);

        $quotationData = [
            'package_id' => $package->id,
            'customer' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '01700000000',
            ],
            'number_of_travelers' => 5,
            'travel_date' => now()->addMonth()->format('Y-m-d'),
            'special_requirements' => '5-star hotels only',
            'budget' => '600000',
        ];

        $response = $this->postJson('/api/v1/quotations', $quotationData, [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'id', 'quotation_number', 'status', 'total_price',
                'discount_amount', 'number_of_travelers',
            ],
        ]);

        // Verify quotation was created
        $this->assertDatabaseHas('quotations', [
            'package_id' => $package->id,
            'status' => 'pending_review',
        ]);
    }

    /**
     * @test
     * Test: GET /api/v1/quotations/{number} - Get quotation details
     */
    public function test_get_quotation_details()
    {
        $quotation = Quotation::factory()->create();

        $response = $this->getJson("/api/v1/quotations/{$quotation->quotation_number}", [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.quotation_number', $quotation->quotation_number);
    }

    // ============================================
    // AUTHENTICATION TESTS
    // ============================================

    /**
     * @test
     * Test: Missing API key
     */
    public function test_missing_api_key()
    {
        $response = $this->getJson('/api/v1/packages', [
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('code', 'MISSING_API_KEY');
    }

    /**
     * @test
     * Test: Missing API secret
     */
    public function test_missing_api_secret()
    {
        $response = $this->getJson('/api/v1/packages', [
            'Authorization' => "Bearer {$this->apiKey->key}",
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('code', 'MISSING_API_SECRET');
    }

    /**
     * @test
     * Test: Invalid API credentials
     */
    public function test_invalid_credentials()
    {
        $response = $this->getJson('/api/v1/packages', [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => 'invalid-secret',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    /**
     * @test
     * Test: Expired API key
     */
    public function test_expired_api_key()
    {
        $this->apiKey->update(['expires_at' => now()->subDay()]);

        $response = $this->getJson('/api/v1/packages', [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('code', 'KEY_EXPIRED');
    }

    /**
     * @test
     * Test: Revoked API key
     */
    public function test_revoked_api_key()
    {
        $this->apiKey->update(['status' => 'revoked']);

        $response = $this->getJson('/api/v1/packages', [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('code', 'KEY_NOT_ACTIVE');
    }

    // ============================================
    // HEALTH CHECK
    // ============================================

    /**
     * @test
     * Test: Health check endpoint
     */
    public function test_health_check()
    {
        $response = $this->getJson('/api/v1/health', [
            'Authorization' => "Bearer {$this->apiKey->key}",
            'X-API-Secret' => $this->apiKeySecret,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('crm_status', 'operational');
    }
}
