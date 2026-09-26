<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Message;

class NewMessage extends CabinetNotification
{
    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(
        public Message $message
    ) {
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
            ->body($senderName . ': ' . CabinetMessage::messagePreview($this->message->content, $this->message->attachments))
            ->icon('chat');

        if ($url) {
            $notification->action('Открыть чат', $url);
        }

        return $notification->toArray();
    }
}
