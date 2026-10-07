<?php

namespace App\Services\Notifications;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel implements NotificationChannelInterface
{
    public function __construct(protected int $tenantId)
    {
    }

    public function send(string $to, ?string $subject, string $body): array
    {
        $token = Setting::where('tenant_id', $this->tenantId)->where('key', 'whatsapp_access_token')->value('value');
        $phoneNumberId = Setting::where('tenant_id', $this->tenantId)->where('key', 'whatsapp_phone_number_id')->value('value');

        if (!$token || !$phoneNumberId) {
            return [
                'status' => 'blocked',
                'detail' => 'No WhatsApp Business API configured for this tenant (Settings: whatsapp_access_token, whatsapp_phone_number_id).',
            ];
        }

        try {
            $response = Http::withToken($token)
                ->post("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $body],
                ]);

            if ($response->successful()) {
                return ['status' => 'delivered', 'detail' => null];
            }

            return ['status' => 'failed', 'detail' => $response->body()];
        } catch (\Throwable $e) {
            Log::error('WhatsApp notification failed', ['error' => $e->getMessage(), 'to' => $to]);
            return ['status' => 'failed', 'detail' => $e->getMessage()];
        }
    }
}
