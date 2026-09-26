<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Room;

/** Учителю: администратор создал для него занятие (ученикам уходит обычное «Новое занятие» — TeacherAssignedLesson). */
class LessonCreatedByAdmin extends CabinetNotification
{
    public bool $broadcastSound = true;

    /** @param  string  $details  «с Алиной Смирновой · первое — чт, 3 октября в 17:00» */
    public function __construct(public Room $room, public string $details = '')
    {
    }

    public function toDatabase(object $notifiable): array
    {
        $details = $this->details !== '' ? ': ' . $this->details : '';

        return CabinetMessage::make('Новое занятие')
            ->body("Администратор создал для вас занятие «{$this->room->name}»{$details}.")
            ->icon('calendar')
            ->action('Открыть занятие', route('cabinet.teacher.lesson', ['room' => $this->room->id]))
            ->toArray();
    }
}
