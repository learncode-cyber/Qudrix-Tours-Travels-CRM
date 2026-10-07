<?php

namespace App\Services\Webhook;

use App\Models\Webhook;
use App\Models\ApiKey;
use Illuminate\Support\Str;
use Illuminate\Pagination\Paginator;

class WebhookService
{
    protected $events = [
        'lead.created',
        'lead.updated',
        'booking.created',
        'booking.updated',
        'booking.confirmed',
        'booking.cancelled',
        'payment.updated',
        'package.updated',
    ];

    public function getAvailableEvents()
    {
        return $this->events;
    }

    public function createWebhook(ApiKey $apiKey, array $data)
    {
        $this->validateEvents($data['events'] ?? []);

        $webhook = new Webhook();
        $webhook->api_key_id = $apiKey->id;
        $webhook->url = $data['url'];
        $webhook->events = $data['events'] ?? [];
        $webhook->is_active = $data['is_active'] ?? true;
        $webhook->secret = Str::random(64);
        $webhook->retry_limit = $data['retry_limit'] ?? 5;
        $webhook->retry_count = 0;
        $webhook->save();

        return $webhook;
    }

    public function updateWebhook(Webhook $webhook, array $data)
    {
        if (isset($data['events'])) {
            $this->validateEvents($data['events']);
            $webhook->events = $data['events'];
        }

        if (isset($data['url'])) {
            $webhook->url = $data['url'];
        }

        if (isset($data['is_active'])) {
            $webhook->is_active = $data['is_active'];
        }

        if (isset($data['retry_limit'])) {
            $webhook->retry_limit = max(1, min(10, (int)$data['retry_limit']));
        }

        $webhook->save();

        return $webhook;
    }

    public function deleteWebhook(Webhook $webhook)
    {
        $webhook->delete();
        return true;
    }

    public function getWebhooksByApiKey(ApiKey $apiKey, $paginate = true)
    {
        $query = Webhook::where('api_key_id', $apiKey->id)
            ->orderByDesc('created_at');

        if ($paginate) {
            return $query->paginate(15);
        }

        return $query->get();
    }

    public function rotateSecret(Webhook $webhook)
    {
        $webhook->secret = Str::random(64);
        $webhook->save();

        return [
            'secret' => $webhook->secret,
            'message' => 'Webhook secret rotated successfully',
        ];
    }

    public function testWebhook(Webhook $webhook)
    {
        $testPayload = [
            'event' => 'test.event',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'message' => 'This is a test webhook delivery',
            ],
        ];

        return (new WebhookDeliveryService())->sendWebhook($webhook, $testPayload);
    }

    public function toggleWebhook(Webhook $webhook)
    {
        $webhook->is_active = !$webhook->is_active;
        $webhook->save();

        return $webhook;
    }

    public function getWebhookLogs(Webhook $webhook, $limit = 50)
    {
        return $webhook->logs()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getWebhookDeliveries(Webhook $webhook, $status = null, $paginate = true)
    {
        $query = $webhook->deliveries();

        if ($status) {
            $query->where('status', $status);
        }

        $query->orderByDesc('created_at');

        if ($paginate) {
            return $query->paginate(15);
        }

        return $query->get();
    }

    public function retryFailedDelivery($deliveryId)
    {
        $delivery = \App\Models\WebhookDelivery::find($deliveryId);

        if (!$delivery) {
            return false;
        }

        $webhook = $delivery->webhook;

        if (!$webhook->canRetry()) {
            return false;
        }

        return (new WebhookDeliveryService())->sendWebhook(
            $webhook,
            $delivery->payload,
            true
        );
    }

    public function getWebhookStatistics(Webhook $webhook)
    {
        $deliveries = $webhook->deliveries;

        return [
            'total_deliveries' => $deliveries->count(),
            'successful' => $deliveries->where('status', 'success')->count(),
            'failed' => $deliveries->where('status', 'failed')->count(),
            'pending' => $deliveries->where('status', 'pending')->count(),
            'success_rate' => $deliveries->count() > 0 
                ? round(($deliveries->where('status', 'success')->count() / $deliveries->count()) * 100, 2)
                : 0,
            'last_triggered' => $webhook->last_triggered_at?->toIso8601String(),
            'last_status' => $webhook->last_triggered_status,
        ];
    }

    private function validateEvents(array $events)
    {
        if (empty($events)) {
            throw new \InvalidArgumentException('At least one event must be selected');
        }

        $invalid = array_diff($events, $this->events);
        if (!empty($invalid)) {
            throw new \InvalidArgumentException('Invalid event(s): ' . implode(', ', $invalid));
        }
    }
}
