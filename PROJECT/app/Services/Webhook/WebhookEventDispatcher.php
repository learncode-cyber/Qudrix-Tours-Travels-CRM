<?php

namespace App\Services\Webhook;

use App\Models\Webhook;
use Illuminate\Support\Facades\Log;

class WebhookEventDispatcher
{
    protected $deliveryService;

    public function __construct()
    {
        $this->deliveryService = new WebhookDeliveryService();
    }

    public function dispatch($eventName, array $data)
    {
        try {
            $webhooks = Webhook::where('is_active', true)
                ->where(function ($query) {
                    $query->where('api_key_id', '!=', null);
                })
                ->get();

            foreach ($webhooks as $webhook) {
                if ($this->webhookListensToEvent($webhook, $eventName)) {
                    $this->sendWebhookEvent($webhook, $eventName, $data);
                }
            }
        } catch (\Exception $e) {
            Log::error('Webhook dispatch error: ' . $e->getMessage());
        }
    }

    public function dispatchLeadCreated($lead)
    {
        $this->dispatch('lead.created', [
            'id' => $lead->id,
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'source' => $lead->source,
            'status' => $lead->status,
            'created_at' => $lead->created_at->toIso8601String(),
        ]);
    }

    public function dispatchLeadUpdated($lead)
    {
        $this->dispatch('lead.updated', [
            'id' => $lead->id,
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'status' => $lead->status,
            'updated_at' => $lead->updated_at->toIso8601String(),
        ]);
    }

    public function dispatchBookingCreated($booking)
    {
        $this->dispatch('booking.created', [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'customer_id' => $booking->customer_id,
            'package_id' => $booking->package_id,
            'total_price' => $booking->total_price,
            'status' => $booking->status,
            'created_at' => $booking->created_at->toIso8601String(),
        ]);
    }

    public function dispatchBookingUpdated($booking)
    {
        $this->dispatch('booking.updated', [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'status' => $booking->status,
            'updated_at' => $booking->updated_at->toIso8601String(),
        ]);
    }

    public function dispatchBookingConfirmed($booking)
    {
        $this->dispatch('booking.confirmed', [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'customer_id' => $booking->customer_id,
            'confirmed_at' => now()->toIso8601String(),
        ]);
    }

    public function dispatchBookingCancelled($booking, $reason = null)
    {
        $this->dispatch('booking.cancelled', [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'reason' => $reason,
            'cancelled_at' => now()->toIso8601String(),
        ]);
    }

    public function dispatchPaymentUpdated($payment)
    {
        $this->dispatch('payment.updated', [
            'id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'amount' => $payment->amount,
            'status' => $payment->status,
            'method' => $payment->method,
            'updated_at' => $payment->updated_at->toIso8601String(),
        ]);
    }

    public function dispatchPackageUpdated($package)
    {
        $this->dispatch('package.updated', [
            'id' => $package->id,
            'name' => $package->name,
            'type' => $package->type,
            'price' => $package->price,
            'availability' => $package->availability,
            'updated_at' => $package->updated_at->toIso8601String(),
        ]);
    }

    protected function webhookListensToEvent($webhook, $eventName)
    {
        if (!is_array($webhook->events)) {
            return false;
        }

        return in_array($eventName, $webhook->events);
    }

    protected function sendWebhookEvent($webhook, $eventName, array $data)
    {
        $payload = [
            'event' => $eventName,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
            'api_version' => 'v1',
        ];

        return $this->deliveryService->sendWebhook($webhook, $payload);
    }
}
