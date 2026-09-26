<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Room;
use App\Models\User;

class TeacherAssignedLesson extends CabinetNotification
{
    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(
        public Room $room,
        public User $teacher
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Новое занятие')
            ->body($this->teacher->name . ': занятие «' . $this->room->name . '» теперь в вашем расписании')
            ->icon('calendar')
            ->action('Открыть занятие', route('cabinet.student.lesson', $this->room))
            ->toArray();
    }

}
