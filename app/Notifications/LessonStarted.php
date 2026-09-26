<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Room;

class LessonStarted extends CabinetNotification
{
    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(
        public Room $room
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Занятие началось')
            ->body("Занятие «{$this->room->name}» началось — можно входить в класс")
            ->icon('video')
            ->action('Войти в класс', $this->getWebPushUrl($notifiable))
            ->toArray();
    }

    /**
     * Get URL for push notification click action.
     */
    public function getWebPushUrl(object $notifiable): string
    {
        // Учитель — на страницу занятия, ученик — сразу ко входу в класс
        return $notifiable->id === $this->room->user_id
            ? route('cabinet.teacher.lesson', $this->room)
            : route('rooms.join', $this->room);
    }
}
