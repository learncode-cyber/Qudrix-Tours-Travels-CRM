<?php

namespace App\Services\Notifications;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramChannel implements NotificationChannelInterface
{
    public function __construct(protected int $tenantId)
    {
    }

    /**
     * $to is the Telegram chat_id for this recipient (Telegram has no
     * concept of routing by email/phone — the chat_id must already be
     * known, typically captured when the user first messages the bot).
     */
    public function send(string $to, ?string $subject, string $body): array
    {
        $botToken = Setting::where('tenant_id', $this->tenantId)->where('key', 'telegram_bot_token')->value('value');

        if (!$botToken) {
            return [
                'status' => 'blocked',
                'detail' => 'No Telegram bot configured for this tenant (Settings: telegram_bot_token).',
            ];
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $to,
                'text' => $body,
            ]);

            if ($response->successful()) {
                return ['status' => 'delivered', 'detail' => null];
            }

            return ['status' => 'failed', 'detail' => $response->body()];
        } catch (\Throwable $e) {
            Log::error('Telegram notification failed', ['error' => $e->getMessage(), 'to' => $to]);
            return ['status' => 'failed', 'detail' => $e->getMessage()];
        }
    }
}
