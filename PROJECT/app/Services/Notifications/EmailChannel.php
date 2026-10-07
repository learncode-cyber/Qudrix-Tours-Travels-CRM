<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailChannel implements NotificationChannelInterface
{
    public function send(string $to, ?string $subject, string $body): array
    {
        $mailer = config('mail.default');

        // No real SMTP/API mailer configured — honest BLOCKED, not a
        // fake "delivered". 'log' and null are not real delivery.
        if (empty($mailer) || $mailer === 'log' || empty(config('mail.mailers.smtp.host'))) {
            return [
                'status' => 'blocked',
                'detail' => 'No mail transport configured (MAIL_MAILER/MAIL_HOST not set). Configure config/mail.php + .env to enable.',
            ];
        }

        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject ?? 'Notification');
            });

            return ['status' => 'delivered', 'detail' => null];
        } catch (\Throwable $e) {
            Log::error('Email notification failed', ['error' => $e->getMessage(), 'to' => $to]);
            return ['status' => 'failed', 'detail' => $e->getMessage()];
        }
    }
}
