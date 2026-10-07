<?php
namespace App\Services;

use App\Models\ConversionEvent;
use App\Models\TrackingConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MASTER_PROJECT_AUDIT.md P2. Mirrors the AI provider adapter pattern
 * (app/Services/AI/Adapters) — a real HTTP integration, not a stub, but
 * one that cannot actually be exercised in this sandbox because no tenant
 * has real Meta/Google credentials configured. Without a TrackingConfig,
 * every send is marked 'not_configured' rather than silently pretending
 * to succeed.
 */
class ConversionTrackingService
{
    public function send(ConversionEvent $event): ConversionEvent
    {
        $config = TrackingConfig::where('tenant_id', $event->tenant_id)->first();

        if (!$config) {
            $event->update(['meta_status' => 'not_configured', 'ga4_status' => 'not_configured']);
            return $event;
        }

        if ($config->metaConfigured()) {
            $this->sendToMeta($event, $config);
        }

        if ($config->ga4Configured()) {
            $this->sendToGa4($event, $config);
        }

        return $event->fresh();
    }

    protected function sendToMeta(ConversionEvent $event, TrackingConfig $config): void
    {
        try {
            $response = Http::timeout(15)->post(
                "https://graph.facebook.com/v18.0/{$config->meta_pixel_id}/events",
                [
                    'access_token' => $config->meta_conversions_api_token,
                    'data' => [[
                        'event_name' => $this->mapEventNameToMeta($event->event_name),
                        'event_time' => now()->timestamp,
                        'action_source' => 'system_generated',
                        'custom_data' => array_filter([
                            'value' => $event->event_value,
                            'currency' => $event->currency,
                        ]),
                    ]],
                ]
            );

            $event->update([
                'meta_status' => $response->successful() ? 'sent' : 'failed',
                'meta_sent_at' => now(),
                'meta_response' => substr($response->body(), 0, 2000),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Meta Conversions API send failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
            $event->update(['meta_status' => 'failed', 'meta_response' => substr($e->getMessage(), 0, 2000)]);
        }
    }

    protected function sendToGa4(ConversionEvent $event, TrackingConfig $config): void
    {
        try {
            $response = Http::timeout(15)->post(
                "https://www.google-analytics.com/mp/collect?measurement_id={$config->ga4_measurement_id}&api_secret={$config->ga4_api_secret}",
                [
                    'client_id' => (string) ($event->lead_id ?? $event->customer_id ?? 'unknown'),
                    'events' => [[
                        'name' => $event->event_name,
                        'params' => array_filter([
                            'value' => $event->event_value,
                            'currency' => $event->currency,
                        ]),
                    ]],
                ]
            );

            // GA4's Measurement Protocol returns 204 with no body on success
            // and does not echo back validation errors on this endpoint.
            $event->update([
                'ga4_status' => $response->successful() ? 'sent' : 'failed',
                'ga4_sent_at' => now(),
                'ga4_response' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('GA4 Measurement Protocol send failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
            $event->update(['ga4_status' => 'failed', 'ga4_response' => substr($e->getMessage(), 0, 2000)]);
        }
    }

    protected function mapEventNameToMeta(string $eventName): string
    {
        return match ($eventName) {
            'qualified_lead' => 'Lead',
            'application_started' => 'InitiateCheckout',
            'payment' => 'Purchase',
            'conversion' => 'CompleteRegistration',
            default => 'CustomEvent',
        };
    }
}
