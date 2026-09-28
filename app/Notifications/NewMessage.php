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
        $senderName = $this->message->user->name;
        $roomId = $this->message->room_id;
        $personalId = $this->message->personal_chat_id;

        // Determine the correct URL based on user role
        $url = null;
        try {
            $url = \App\Services\MessengerService::url($notifiable, $roomId, personal: $personalId);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to generate messenger URL for notification: " . $e->getMessage());
        }

        $title = $personalId ? "Новое сообщение от {$senderName}" : "Новое сообщение в «{$this->message->room?->name}»";

        $notification = CabinetMessage::make($title)
            ->body($senderName . ': ' . CabinetMessage::messagePreview($this->message->content, $this->message->attachments))
            ->icon('chat');

        if ($url) {
            $notification->action('Открыть чат', $url);
        }

        return $notification->toArray();
    }
}
