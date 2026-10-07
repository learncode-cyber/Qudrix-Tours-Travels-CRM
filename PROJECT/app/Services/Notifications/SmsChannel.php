<?php

namespace App\Services\Notifications;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsChannel implements NotificationChannelInterface
{
    public function __construct(protected int $tenantId)
    {
    }

    public function send(string $to, ?string $subject, string $body): array
    {
        $sid = Setting::where('tenant_id', $this->tenantId)->where('key', 'twilio_account_sid')->value('value');
        $token = Setting::where('tenant_id', $this->tenantId)->where('key', 'twilio_auth_token')->value('value');
        $from = Setting::where('tenant_id', $this->tenantId)->where('key', 'twilio_from_number')->value('value');

        if (!$sid || !$token || !$from) {
            return [
                'status' => 'blocked',
                'detail' => 'No SMS provider configured for this tenant (Settings: twilio_account_sid, twilio_auth_token, twilio_from_number).',
            ];
        }

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $body,
                ]);

            if ($response->successful()) {
                return ['status' => 'delivered', 'detail' => null];
            }

            return ['status' => 'failed', 'detail' => $response->body()];
        } catch (\Throwable $e) {
            Log::error('SMS notification failed', ['error' => $e->getMessage(), 'to' => $to]);
            return ['status' => 'failed', 'detail' => $e->getMessage()];
        }
    }
}
