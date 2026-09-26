<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Message;
use App\Notifications\Traits\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class NewMessage extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(
        public Message $message
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = \NotificationChannels\WebPush\WebPushChannel::class;
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        $roomName = $this->message->room->name;
        $senderName = $this->message->user->name;
        $roomId = $this->message->room_id;

        // Determine the correct URL based on user role
        $url = null;
        try {
            $url = \App\Services\MessengerService::url($notifiable, $roomId);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to generate messenger URL for notification: " . $e->getMessage());
        }

        $notification = CabinetMessage::make("Новое сообщение в «{$roomName}»")
            ->body("{$senderName}: " . \Illuminate\Support\Str::limit($this->message->content, 50))
            ->icon('chat');

        if ($url) {
            $notification->action('Открыть чат', $url);
        }

        return $notification->toArray();
    }
}
