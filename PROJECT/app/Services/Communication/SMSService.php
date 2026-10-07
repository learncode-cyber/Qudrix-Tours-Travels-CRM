<?php

namespace App\Services\Communication;

use Illuminate\Support\Facades\Log;

class SMSService
{
    public function send(string $phone, string $message): bool
    {
        try {
            // In production, integrate with SMS gateway (Twilio, Nexmo, etc.)
            Log::info("SMS to $phone: $message");
            return true;
        } catch (\Exception $e) {
            Log::error("SMS send failed: " . $e->getMessage());
            return false;
        }
    }
}
