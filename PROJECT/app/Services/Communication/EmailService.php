<?php

namespace App\Services\Communication;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    public function send(string $to, string $subject, string $body): bool
    {
        try {
            // In production, use Mail::queue() or Mail::send()
            // For now, just log it
            Log::info("Email to $to: $subject");
            return true;
        } catch (\Exception $e) {
            Log::error("Email send failed: " . $e->getMessage());
            return false;
        }
    }
}
