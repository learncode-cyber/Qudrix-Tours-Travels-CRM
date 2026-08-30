<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Real client for Telegram's public Bot API (a stable, documented
// third-party contract — not an invented endpoint; the directive names
// Telegram explicitly as a required integration, §17). The bot token is
// read only from config('services.telegram.bot_token'), which is
// server-side-only (never exposed to the frontend).
class TelegramNotificationService
{
    public function isConfigured(): bool
    {
        return filled(config('services.telegram.bot_token'));
    }

    public function send(string $chatId, string $message): array
    {
        if (!$this->isConfigured()) {
            return ['sent' => false, 'reason' => 'CONTRACT REQUIRED: TELEGRAM_BOT_TOKEN is not configured'];
        }

        $token = config('services.telegram.bot_token');

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
            ]);

            if (!$response->successful()) {
                Log::warning('Telegram notification failed', ['status' => $response->status(), 'body' => $response->body()]);
                return ['sent' => false, 'reason' => 'Telegram API returned ' . $response->status()];
            }

            return ['sent' => true, 'message_id' => $response->json('result.message_id')];
        } catch (\Throwable $e) {
            Log::error('Telegram notification exception', ['error' => $e->getMessage()]);
            return ['sent' => false, 'reason' => $e->getMessage()];
        }
    }
}
