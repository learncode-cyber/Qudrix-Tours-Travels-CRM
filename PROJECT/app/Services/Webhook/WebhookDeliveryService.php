<?php

namespace App\Services\Webhook;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookDeliveryService
{
    protected $maxRetries = 5;
    protected $timeout = 30;

    public function sendWebhook(Webhook $webhook, array $payload, $isRetry = false)
    {
        $deliveryId = Str::uuid()->toString();

        // Create delivery record
        $delivery = new WebhookDelivery();
        $delivery->webhook_id = $webhook->id;
        $delivery->delivery_id = $deliveryId;
        $delivery->event = $payload['event'] ?? 'unknown.event';
        $delivery->payload = $payload;
        $delivery->status = 'pending';
        $delivery->attempt = $isRetry ? ($this->getLastDeliveryAttempt($webhook) + 1) : 1;
        $delivery->save();

        try {
            $signature = (new HmacSignatureService())->generateSignature(
                json_encode($payload),
                $webhook->secret
            );

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-ID' => $deliveryId,
                    'X-Webhook-Event' => $payload['event'] ?? 'unknown',
                    'User-Agent' => 'QUDRIX-Webhook/1.0',
                    'Content-Type' => 'application/json',
                ])
                ->post($webhook->url, $payload);

            $this->handleSuccessfulDelivery($delivery, $response, $webhook);

            return true;
        } catch (\Exception $e) {
            $this->handleFailedDelivery($delivery, $e, $webhook);

            if ($webhook->canRetry()) {
                $webhook->incrementRetry();
                $this->scheduleRetry($webhook, $delivery);
            }

            return false;
        }
    }

    public function scheduleRetry(Webhook $webhook, WebhookDelivery $delivery)
    {
        $delay = $this->calculateBackoffDelay($delivery->attempt);

        // This would normally dispatch a job to queue
        // For now, log it for later processing
        WebhookLog::create([
            'webhook_id' => $webhook->id,
            'delivery_id' => $delivery->id,
            'message' => 'Scheduled retry attempt #' . ($delivery->attempt + 1),
            'status' => 'scheduled',
            'retry_at' => now()->addSeconds($delay),
        ]);
    }

    public function processScheduledRetries()
    {
        $logs = WebhookLog::where('status', 'scheduled')
            ->where('retry_at', '<=', now())
            ->get();

        foreach ($logs as $log) {
            $delivery = WebhookDelivery::find($log->delivery_id);
            if ($delivery) {
                $webhook = $delivery->webhook;
                if ($webhook->canRetry()) {
                    $this->sendWebhook($webhook, $delivery->payload, true);
                }
            }
        }
    }

    private function handleSuccessfulDelivery($delivery, $response, Webhook $webhook)
    {
        $delivery->status = 'success';
        $delivery->response_status = $response->status();
        $delivery->response_body = $response->body();
        $delivery->delivered_at = now();
        $delivery->save();

        $webhook->update([
            'last_triggered_at' => now(),
            'last_triggered_status' => 'success',
            'retry_count' => 0,
        ]);

        WebhookLog::create([
            'webhook_id' => $webhook->id,
            'delivery_id' => $delivery->id,
            'message' => "Webhook delivered successfully (HTTP {$response->status()})",
            'status' => 'success',
        ]);
    }

    private function handleFailedDelivery($delivery, $exception, Webhook $webhook)
    {
        $delivery->status = 'failed';
        $delivery->error_message = $exception->getMessage();
        $delivery->failed_at = now();
        $delivery->save();

        $webhook->update([
            'last_triggered_at' => now(),
            'last_triggered_status' => 'failed',
        ]);

        WebhookLog::create([
            'webhook_id' => $webhook->id,
            'delivery_id' => $delivery->id,
            'message' => 'Delivery failed: ' . $exception->getMessage(),
            'status' => 'failed',
        ]);
    }

    private function calculateBackoffDelay($attempt)
    {
        // Exponential backoff: 5, 25, 125, 625, 3125 seconds
        $base = 5;
        $exponent = $attempt - 1;
        $delay = $base * pow(5, $exponent);

        // Cap at 24 hours
        return min($delay, 86400);
    }

    private function getLastDeliveryAttempt(Webhook $webhook)
    {
        $lastDelivery = $webhook->deliveries()
            ->orderByDesc('attempt')
            ->first();

        return $lastDelivery?->attempt ?? 0;
    }

    public function getDeliveryStatus($deliveryId)
    {
        $delivery = WebhookDelivery::where('delivery_id', $deliveryId)->first();

        if (!$delivery) {
            return null;
        }

        return [
            'id' => $delivery->delivery_id,
            'event' => $delivery->event,
            'status' => $delivery->status,
            'attempt' => $delivery->attempt,
            'response_status' => $delivery->response_status,
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'failed_at' => $delivery->failed_at?->toIso8601String(),
        ];
    }
}
