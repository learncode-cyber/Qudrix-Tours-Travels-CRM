<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PublicPackageController;
use App\Http\Controllers\Api\PublicBookingController;
use App\Http\Controllers\Api\PublicQuotationController;
use App\Http\Controllers\Admin\AdminApiKeyController;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
| These routes are publicly accessible but require API key authentication
| via middleware. Used by website and third-party integrations.
|
| Prefix: /api/v1
| Authentication: API Key (Bearer token) + Secret header
|
*/

Route::middleware(['api', 'throttle:api'])->group(function () {
    
    // ============================================
    // PACKAGES (Public Listing)
    // ============================================
    Route::prefix('packages')->group(function () {
        // Get all packages with filtering/pagination
        Route::get('/', [PublicPackageController::class, 'index'])
            ->name('api.packages.index');
        
        // Get single package details
        Route::get('/{id}', [PublicPackageController::class, 'show'])
            ->name('api.packages.show')
            ->where('id', '[0-9]+');
    });

    // ============================================
    // BOOKINGS
    // ============================================
    Route::prefix('bookings')->group(function () {
        // Create new booking from website
        Route::post('/', [PublicBookingController::class, 'store'])
            ->name('api.bookings.store');

        // Get booking status
        Route::get('/{reference}', [PublicBookingController::class, 'show'])
            ->name('api.bookings.show');
    });

    // ============================================
    // QUOTATIONS (Custom Quotes)
    // ============================================
    Route::prefix('quotations')->group(function () {
        // Request custom quotation
        Route::post('/', [PublicQuotationController::class, 'store'])
            ->name('api.quotations.store');

        // Get quotation details
        Route::get('/{number}', [PublicQuotationController::class, 'show'])
            ->name('api.quotations.show');
    });

    // ============================================
    // HEALTH & STATUS
    // ============================================
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'API is healthy',
            'timestamp' => now()->toIso8601String(),
            'api_version' => 'v1',
            'crm_status' => 'operational',
        ]);
    })->name('api.health')->withoutMiddleware(['throttle:api']);
});

// ============================================
// ADMIN API ROUTES (Separate prefix, different auth)
// ============================================
Route::prefix('admin/api')->middleware(['auth:api', 'throttle:admin-api'])->group(function () {
    
    Route::prefix('api-keys')->group(function () {
        // List all API keys
        Route::get('/', [AdminApiKeyController::class, 'index'])
            ->name('admin.api-keys.index');
        
        // Create new API key
        Route::post('/', [AdminApiKeyController::class, 'store'])
            ->name('admin.api-keys.store');
        
        // Get API key details
        Route::get('/{id}', [AdminApiKeyController::class, 'show'])
            ->name('admin.api-keys.show')
            ->where('id', '[0-9]+');
        
        // Rotate API key (create new secret, revoke old)
        Route::post('/{id}/rotate', [AdminApiKeyController::class, 'rotate'])
            ->name('admin.api-keys.rotate')
            ->where('id', '[0-9]+');
        
        // Revoke API key
        Route::post('/{id}/revoke', [AdminApiKeyController::class, 'revoke'])
            ->name('admin.api-keys.revoke')
            ->where('id', '[0-9]+');
        
        // Get API usage statistics
        Route::get('/{id}/usage', [AdminApiKeyController::class, 'usage'])
            ->name('admin.api-keys.usage')
            ->where('id', '[0-9]+');
    });

    // Test connection with API credentials
    Route::post('/test-connection', [AdminApiKeyController::class, 'testConnection'])
        ->name('admin.api.test-connection');
});

// ============================================
// API DOCUMENTATION
// ============================================
Route::get('/docs', function () {
    $openApiPath = base_path('docs/OPENAPI.yaml');
    $hasOpenApi = file_exists($openApiPath);
    
    return response()->json([
        'success' => true,
        'message' => 'API Documentation Available',
        'api_version' => '1.0.0',
        'endpoints' => [
            'packages' => [
                'GET /api/v1/packages' => 'List all packages',
                'GET /api/v1/packages/{id}' => 'Get package details',
            ],
            'bookings' => [
                'POST /api/v1/bookings' => 'Create booking',
                'GET /api/v1/bookings/{reference}' => 'Get booking status',
            ],
            'quotations' => [
                'POST /api/v1/quotations' => 'Request quotation',
                'GET /api/v1/quotations/{number}' => 'Get quotation details',
            ],
        ],
        'admin_endpoints' => [
            'POST /admin/api/api-keys' => 'Create API key',
            'GET /admin/api/api-keys' => 'List API keys',
            'GET /admin/api/api-keys/{id}' => 'Get API key details',
            'POST /admin/api/api-keys/{id}/rotate' => 'Rotate API key',
            'POST /admin/api/api-keys/{id}/revoke' => 'Revoke API key',
            'GET /admin/api/api-keys/{id}/usage' => 'Get usage statistics',
            'POST /admin/api/test-connection' => 'Test API connection',
        ],
        'authentication' => [
            'type' => 'API Key (Bearer Token) + Secret',
            'headers' => [
                'Authorization' => 'Bearer ak_xxxxx',
                'X-API-Secret' => 'sk_xxxxx',
            ],
        ],
        'documentation' => [
            'openapi_spec' => $hasOpenApi ? '/docs/openapi.yaml' : 'Not available',
            'integration_guide' => '/docs/WEBSITE_CRM_INTEGRATION.md',
            'authentication_guide' => '/docs/AUTHENTICATION.md',
        ],
    ]);
})->name('api.docs')->withoutMiddleware(['auth:api']);

// Webhook Management (Admin)
Route::middleware(['auth:api'])->prefix('admin/api/webhooks')->group(function () {
    Route::get('/', 'App\Http\Controllers\Admin\AdminWebhookController@index')->name('admin.webhooks.index');
    Route::get('/events', 'App\Http\Controllers\Admin\AdminWebhookController@getAvailableEvents')->name('admin.webhooks.events');
    Route::post('/', 'App\Http\Controllers\Admin\AdminWebhookController@store')->name('admin.webhooks.store');
    Route::get('/{webhook}', 'App\Http\Controllers\Admin\AdminWebhookController@show')->name('admin.webhooks.show');
    Route::put('/{webhook}', 'App\Http\Controllers\Admin\AdminWebhookController@update')->name('admin.webhooks.update');
    Route::delete('/{webhook}', 'App\Http\Controllers\Admin\AdminWebhookController@destroy')->name('admin.webhooks.destroy');
    Route::post('/{webhook}/rotate-secret', 'App\Http\Controllers\Admin\AdminWebhookController@rotateSecret')->name('admin.webhooks.rotate');
    Route::post('/{webhook}/toggle', 'App\Http\Controllers\Admin\AdminWebhookController@toggle')->name('admin.webhooks.toggle');
    Route::get('/{webhook}/deliveries', 'App\Http\Controllers\Admin\AdminWebhookController@deliveries')->name('admin.webhooks.deliveries');
    Route::get('/{webhook}/logs', 'App\Http\Controllers\Admin\AdminWebhookController@logs')->name('admin.webhooks.logs');
    Route::post('/{webhook}/test', 'App\Http\Controllers\Admin\AdminWebhookController@test')->name('admin.webhooks.test');
    Route::post('/{webhook}/retry', 'App\Http\Controllers\Admin\AdminWebhookController@retryDelivery')->name('admin.webhooks.retry');
    Route::get('/{webhook}/statistics', 'App\Http\Controllers\Admin\AdminWebhookController@statistics')->name('admin.webhooks.statistics');
});

// Website Integration Management (Admin)
Route::middleware(['auth:api'])->prefix('admin/api/integrations')->group(function () {
    Route::get('/', 'App\\Http\\Controllers\\Admin\\IntegrationController@index')->name('admin.integrations.index');
    Route::post('/', 'App\\Http\\Controllers\\Admin\\IntegrationController@store')->name('admin.integrations.store');
    Route::get('/{id}', 'App\\Http\\Controllers\\Admin\\IntegrationController@show')->name('admin.integrations.show');
    Route::put('/{id}', 'App\\Http\\Controllers\\Admin\\IntegrationController@update')->name('admin.integrations.update');
    Route::delete('/{id}', 'App\\Http\\Controllers\\Admin\\IntegrationController@destroy')->name('admin.integrations.destroy');
    Route::post('/{id}/credentials', 'App\\Http\\Controllers\\Admin\\IntegrationController@saveCredentials')->name('admin.integrations.credentials');
    Route::post('/{id}/test-connection', 'App\\Http\\Controllers\\Admin\\IntegrationController@testConnection')->name('admin.integrations.test');
    Route::get('/{id}/audit-logs', 'App\\Http\\Controllers\\Admin\\IntegrationController@auditLogs')->name('admin.integrations.audit-logs');
});

// Fallback for undefined routes
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint not found',
        'code' => 'NOT_FOUND',
        'hint' => 'Please check the API documentation at /api/docs',
    ], 404);
});
