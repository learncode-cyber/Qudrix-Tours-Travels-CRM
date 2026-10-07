<?php

namespace App\Services\Notifications;

use App\Models\NotificationTemplate;
use App\Models\Notification;

class NotificationService
{
    /**
     * @param string $eventKey e.g. 'booking_confirmed', 'visa_approved', 'payment_reminder', 'follow_up_due'
     * @param string $channel 'in_app', 'email', 'sms', 'whatsapp', 'telegram'
     * @param array $recipient ['to' => string (email/phone/chat_id), 'user_id' => ?int, 'customer_id' => ?int]
     * @param array $data variables available to the template, e.g. ['customer_name' => ..., 'booking_number' => ...]
     * @param array|null $relatedEntity ['type' => string, 'id' => int]
     */
    public function notify(int $tenantId, string $eventKey, string $channel, array $recipient, array $data, ?array $relatedEntity = null): array
    {
        $template = NotificationTemplate::where('tenant_id', $tenantId)
            ->where('event_key', $eventKey)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();

        if ($template) {
            $rendered = $template->render($data);
            $subject = $rendered['subject'];
            $body = $rendered['body'];
        } else {
            // No template configured — fall back to a generic message
            // built from event_key rather than silently doing nothing,
            // but this is clearly a placeholder body, not polished copy.
            $subject = ucwords(str_replace('_', ' ', $eventKey));
            $body = $data['default_message'] ?? $subject;
        }

        $result = $channel === 'in_app'
            ? ['status' => 'delivered', 'detail' => null] // writing the row below IS the delivery
            : $this->resolveChannel($channel, $tenantId)->send($recipient['to'] ?? '', $subject, $body);

        $notification = Notification::create([
            'tenant_id' => $tenantId,
            'user_id' => $recipient['user_id'] ?? null,
            'customer_id' => $recipient['customer_id'] ?? null,
            'type' => $eventKey,
            'title' => $subject ?? $eventKey,
            'message' => $body,
            'data' => $data,
            'channel' => $channel,
            'delivery_status' => $result['status'],
            'delivery_detail' => $result['detail'],
            'related_entity_type' => $relatedEntity['type'] ?? null,
            'related_entity_id' => $relatedEntity['id'] ?? null,
        ]);

        return ['notification' => $notification, 'result' => $result];
    }

    protected function resolveChannel(string $channel, int $tenantId): NotificationChannelInterface
    {
        return match ($channel) {
            'email' => new EmailChannel(),
            'sms' => new SmsChannel($tenantId),
            'whatsapp' => new WhatsAppChannel($tenantId),
            'telegram' => new TelegramChannel($tenantId),
            default => throw new \InvalidArgumentException("Unknown notification channel: {$channel}"),
        };
    }
}
