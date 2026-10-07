<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\ApiKey;
use App\Services\Webhook\WebhookService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * FIX (2026-09-22, cross-tenant IDOR — see routes/api-public.php for the
 * matching middleware fix): every method here that receives a Webhook via
 * route-model binding resolved it by ID alone with no tenant check at all,
 * even though `webhooks.tenant_id` exists. Any authenticated user from any
 * tenant could view, edit, delete, test, rotate the secret of, or read
 * delivery logs for another tenant's webhook just by guessing/incrementing
 * the ID. index()/store() had the same gap (no tenant filter on the list
 * query, no ownership check on the referenced api_key_id).
 *
 * Fixed with an explicit `authorizeWebhook()` check in every method that
 * receives a Webhook, on top of the 'tenant' middleware now added to the
 * route group — defense in depth, since relying on the global scope alone
 * is exactly what let this go unnoticed (the scope depends on the route
 * group actually including 'tenant', which it didn't).
 */
class AdminWebhookController extends Controller
{
    protected $webhookService;

    public function __construct()
    {
        $this->webhookService = new WebhookService();
        $this->middleware('auth:api');
    }

    /**
     * FIX (2026-09-25): this controller's route group uses 'auth:api'
     * (Laravel's built-in guard middleware), not this codebase's usual
     * 'jwt.auth' alias — only the latter sets the custom $request->user
     * property (see app/Http/Middleware/JwtAuth.php). Referencing
     * $request->user directly here would null-reference on every call.
     * Falls back to auth()->user(), same as TenantMiddleware does.
     */
    protected function currentTenantId(Request $request): int
    {
        $user = $request->user ?? auth()->user();

        if (!$user?->tenant_id) {
            abort(401, 'Unauthenticated.');
        }

        return $user->tenant_id;
    }

    /** 404s (not 403, to avoid confirming the ID exists at all) if the webhook belongs to another tenant. */
    protected function authorizeWebhook(Request $request, Webhook $webhook): void
    {
        if ($webhook->tenant_id !== $this->currentTenantId($request)) {
            throw new NotFoundHttpException();
        }
    }

    public function index(Request $request)
    {
        $apiKeyId = $request->query('api_key_id');
        $status = $request->query('status'); // active, inactive

        $query = Webhook::where('tenant_id', $this->currentTenantId($request));

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

    public function getAvailableEvents()
    {
        return response()->json([
            'success' => true,
            'events' => $this->webhookService->getAvailableEvents(),
        ]);
    }

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

        // FIX: api_key_id existing at all isn't enough — it must belong to
        // this tenant, or a user could attach a webhook to another
        // tenant's API key.
        $apiKey = ApiKey::where('tenant_id', $this->currentTenantId($request))->find($validated['api_key_id']);

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

    public function show(Request $request, Webhook $webhook)
    {
        $this->authorizeWebhook($request, $webhook);
        $stats = $this->webhookService->getWebhookStatistics($webhook);

        return response()->json([
            'success' => true,
            'data' => $webhook,
            'statistics' => $stats,
        ]);
    }

    public function update(Request $request, Webhook $webhook)
    {
        $this->authorizeWebhook($request, $webhook);

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

    public function destroy(Request $request, Webhook $webhook)
    {
        $this->authorizeWebhook($request, $webhook);
        $this->webhookService->deleteWebhook($webhook);

        return response()->json([
            'success' => true,
            'message' => 'Webhook deleted successfully',
        ]);
    }

    public function rotateSecret(Request $request, Webhook $webhook)
    {
        $this->authorizeWebhook($request, $webhook);
        $result = $this->webhookService->rotateSecret($webhook);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function toggle(Request $request, Webhook $webhook)
    {
        $this->authorizeWebhook($request, $webhook);
        $webhook = $this->webhookService->toggleWebhook($webhook);

        return response()->json([
            'success' => true,
            'data' => $webhook,
            'message' => 'Webhook ' . ($webhook->is_active ? 'activated' : 'deactivated'),
        ]);
    }

    public function deliveries(Request $request, Webhook $webhook)
    {
        $this->authorizeWebhook($request, $webhook);
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

    public function logs(Request $request, Webhook $webhook)
    {
        $this->authorizeWebhook($request, $webhook);
        $logs = $this->webhookService->getWebhookLogs($webhook);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    public function test(Request $request, Webhook $webhook)
    {
        $this->authorizeWebhook($request, $webhook);
        $result = $this->webhookService->testWebhook($webhook);

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Test webhook sent successfully' : 'Failed to send test webhook',
        ]);
    }

    public function retryDelivery(Request $request, Webhook $webhook)
    {
        $this->authorizeWebhook($request, $webhook);
        $deliveryId = $request->input('delivery_id');

        if (!$deliveryId) {
            return response()->json([
                'success' => false,
                'message' => 'delivery_id is required',
            ], 400);
        }

        // FIX: WebhookService::retryFailedDelivery() looks up the delivery
        // purely by ID and retries against *its own* webhook relation —
        // it never checks the route's $webhook at all. Without this check,
        // the $webhook binding above is decorative and a caller could pass
        // any tenant's delivery_id here to retry someone else's webhook.
        $delivery = \App\Models\WebhookDelivery::find($deliveryId);
        if (!$delivery || $delivery->webhook_id !== $webhook->id) {
            return response()->json(['success' => false, 'message' => 'Delivery not found for this webhook'], 404);
        }

        $result = $this->webhookService->retryFailedDelivery($deliveryId);

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Retry scheduled successfully' : 'Failed to schedule retry',
        ]);
    }

    public function statistics(Request $request, Webhook $webhook)
    {
        $this->authorizeWebhook($request, $webhook);
        $stats = $this->webhookService->getWebhookStatistics($webhook);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
