<?php
namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// Fans a notification out to every requested channel and always records
// the real, current in-app row plus (best-effort) delivery outcomes —
// never a fabricated "sent: true".
class NotificationService
{
    public function __construct(private TelegramNotificationService $telegram)
    {
    }

    public function send(int $tenantId, int $userId, string $type, string $title, string $message, array $data = [], array $channels = ['in_app']): array
    {
        $notification = Notification::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        $delivery = ['in_app' => true];

        if (in_array('telegram', $channels)) {
            $user = User::find($userId);
            if ($user?->telegram_chat_id) {
                $result = $this->telegram->send($user->telegram_chat_id, "{$title}\n\n{$message}");
                $delivery['telegram'] = $result;
            } else {
                $delivery['telegram'] = ['sent' => false, 'reason' => 'User has no telegram_chat_id configured'];
            }
        }

        if (in_array('email', $channels)) {
            $user = User::find($userId);
            if ($user?->email) {
                try {
                    Mail::raw($message, function ($mail) use ($user, $title) {
                        $mail->to($user->email)->subject($title);
                    });
                    $delivery['email'] = ['sent' => true];
                } catch (\Throwable $e) {
                    Log::warning('Email notification failed', ['error' => $e->getMessage()]);
                    $delivery['email'] = ['sent' => false, 'reason' => $e->getMessage()];
                }
            } else {
                $delivery['email'] = ['sent' => false, 'reason' => 'User has no email'];
            }
        }

        return ['notification' => $notification, 'delivery' => $delivery];
    }
}
