<?php

namespace App\Services\Communication;

use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function send(string $phone, string $message): bool
    {
        try {
            // In production, integrate with WhatsApp Business API
            Log::info("WhatsApp to $phone: $message");
            return true;
        } catch (\Exception $e) {
            Log::error("WhatsApp send failed: " . $e->getMessage());
            return false;
        }
    }
}
