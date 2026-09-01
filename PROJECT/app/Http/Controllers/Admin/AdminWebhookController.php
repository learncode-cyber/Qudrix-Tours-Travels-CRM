<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\ApiKey;
use App\Services\Webhook\WebhookService;
use Illuminate\Http\Request;

class AdminWebhookController extends Controller
{
    protected $webhookService;

    public function __construct()
    {
        $this->webhookService = new WebhookService();
        $this->middleware('auth:api');
    }

    /**
     * List all webhooks
     */
    public function index(Request $request)
    {
        $apiKeyId = $request->query('api_key_id');
        $status = $request->query('status'); // active, inactive

        $query = Webhook::query();

        if ($apiKeyId) {
            $query->where('api_key_id', $apiKeyId);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $webhooks = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $webhooks->items(),
            'pagination' => [
                'total' => $webhooks->total(),
                'per_page' => $webhooks->perPage(),
                'current_page' => $webhooks->currentPage(),
                'last_page' => $webhooks->lastPage(),
            ],
        ]);
    }

    /**
     * Get available events
     */
    public function getAvailableEvents()
    {
        return response()->json([
            'success' => true,
            'events' => $this->webhookService->getAvailableEvents(),
        ]);
    }

    /**
     * Create webhook
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'api_key_id' => 'required|exists:api_keys,id',
            'url' => 'required|url|max:255',
            'events' => 'required|array|min:1',
            'events.*' => 'string',
            'is_active' => 'boolean',
            'retry_limit' => 'integer|min:1|max:10',
        ]);

        $apiKey = ApiKey::find($validated['api_key_id']);

        if (!$apiKey || $apiKey->is_revoked) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or revoked API key',
            ], 400);
        }

        try {
            $webhook = $this->webhookService->createWebhook($apiKey, $validated);

            return response()->json([
                'success' => true,
                'data' => $webhook,
                'message' => 'Webhook created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get webhook details
     */
    public function show(Webhook $webhook)
    {
        $stats = $this->webhookService->getWebhookStatistics($webhook);

        return response()->json([
            'success' => true,
            'data' => $webhook,
            'statistics' => $stats,
        ]);
    }

    /**
     * Update webhook
     */
    public function update(Request $request, Webhook $webhook)
    {
        $validated = $request->validate([
            'url' => 'url|max:255',
            'events' => 'array|min:1',
            'events.*' => 'string',
            'is_active' => 'boolean',
            'retry_limit' => 'integer|min:1|max:10',
        ]);

        try {
            $webhook = $this->webhookService->updateWebhook($webhook, $validated);

            return response()->json([
                'success' => true,
                'data' => $webhook,
                'message' => 'Webhook updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete webhook
     */
    public function destroy(Webhook $webhook)
    {
        $this->webhookService->deleteWebhook($webhook);

        return response()->json([
            'success' => true,
            'message' => 'Webhook deleted successfully',
        ]);
    }

    /**
     * Rotate webhook secret
     */
    public function rotateSecret(Webhook $webhook)
    {
        $result = $this->webhookService->rotateSecret($webhook);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Toggle webhook active/inactive
     */
    public function toggle(Webhook $webhook)
    {
        $webhook = $this->webhookService->toggleWebhook($webhook);

        return response()->json([
            'success' => true,
            'data' => $webhook,
            'message' => 'Webhook ' . ($webhook->is_active ? 'activated' : 'deactivated'),
        ]);
    }

    /**
     * Get webhook deliveries
     */
    public function deliveries(Request $request, Webhook $webhook)
    {
        $status = $request->query('status');

        $deliveries = $this->webhookService->getWebhookDeliveries($webhook, $status);

        return response()->json([
            'success' => true,
            'data' => $deliveries->items(),
            'pagination' => [
                'total' => $deliveries->total(),
                'per_page' => $deliveries->perPage(),
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
            ],
        ]);
    }

    /**
     * Get webhook logs
     */
    public function logs(Webhook $webhook)
    {
        $logs = $this->webhookService->getWebhookLogs($webhook);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Test webhook
     */
    public function test(Webhook $webhook)
    {
        $result = $this->webhookService->testWebhook($webhook);

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Test webhook sent successfully' : 'Failed to send test webhook',
        ]);
    }

    /**
     * Retry failed delivery
     */
    public function retryDelivery(Request $request, Webhook $webhook)
    {
        $deliveryId = $request->input('delivery_id');

        if (!$deliveryId) {
            return response()->json([
                'success' => false,
                'message' => 'delivery_id is required',
            ], 400);
        }

        $result = $this->webhookService->retryFailedDelivery($deliveryId);

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Retry scheduled successfully' : 'Failed to schedule retry',
        ]);
    }

    /**
     * Get webhook statistics
     */
    public function statistics(Webhook $webhook)
    {
        $stats = $this->webhookService->getWebhookStatistics($webhook);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
