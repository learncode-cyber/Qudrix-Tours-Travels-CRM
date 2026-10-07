<?php

namespace App\Services\Communication;

use App\Models\NotificationTemplate;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class CommunicationService
{
    public function __construct(
        protected EmailService $emailService,
        protected SMSService $smsService,
        protected WhatsAppService $whatsAppService,
    ) {}

    /**
     * Send a notification via specified channels
     * @param string $recipientId User/Contact ID
     * @param string $templateKey NotificationTemplate key
     * @param array $variables Variables to interpolate in template
     * @param array $channels ['email', 'sms', 'whatsapp'] 
     */
    public function send(string $recipientId, string $templateKey, array $variables = [], array $channels = ['email'])
    {
        $template = NotificationTemplate::where('key', $templateKey)->first();
        if (!$template) {
            Log::warning("Template not found: $templateKey");
            return false;
        }

        $results = [];
        foreach ($channels as $channel) {
            $results[$channel] = match($channel) {
                'email' => $this->sendEmail($recipientId, $template, $variables),
                'sms' => $this->sendSMS($recipientId, $template, $variables),
                'whatsapp' => $this->sendWhatsApp($recipientId, $template, $variables),
                default => false,
            };
        }

        // Log notification record
        Notification::create([
            'recipient_id' => $recipientId,
            'template_id' => $template->id,
            'channels' => json_encode(array_keys(array_filter($results))),
            'status' => in_array(true, $results) ? 'sent' : 'failed',
            'sent_at' => in_array(true, $results) ? now() : null,
        ]);

        return in_array(true, $results);
    }

    private function sendEmail(string $recipientId, NotificationTemplate $template, array $variables): bool
    {
        // Placeholder: would fetch user email from database
        // $user = User::find($recipientId);
        // return $this->emailService->send($user->email, $template, $variables);
        Log::info("Email queued for recipient $recipientId using template {$template->key}");
        return true; // Placeholder success
    }

    private function sendSMS(string $recipientId, NotificationTemplate $template, array $variables): bool
    {
        // Placeholder: would fetch user phone from database
        Log::info("SMS queued for recipient $recipientId using template {$template->key}");
        return true; // Placeholder success
    }

    private function sendWhatsApp(string $recipientId, NotificationTemplate $template, array $variables): bool
    {
        // Placeholder: would fetch user phone from database
        Log::info("WhatsApp queued for recipient $recipientId using template {$template->key}");
        return true; // Placeholder success
    }
}
