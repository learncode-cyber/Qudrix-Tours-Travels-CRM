<?php

namespace App\Services\Notifications;

interface NotificationChannelInterface
{
    /**
     * @return array{status: 'delivered'|'blocked'|'failed', detail: ?string}
     */
    public function send(string $to, ?string $subject, string $body): array;
}
