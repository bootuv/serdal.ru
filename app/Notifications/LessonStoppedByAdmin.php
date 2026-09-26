<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\MeetingSession;
use App\Models\Room;

/** Учителю: администратор завершил его идущее занятие (админка, «Завершить занятие»). */
class LessonStoppedByAdmin extends CabinetNotification
{
    public bool $broadcastSound = true;

    public function __construct(public Room $room, public ?MeetingSession $session = null)
    {
    }

    public function toDatabase(object $notifiable): array
    {
        $started = $this->session?->started_at ? ', начатое в ' . $this->session->started_at->format('H:i') : '';

        return CabinetMessage::make('Занятие завершено администратором')
            ->body("Администратор завершил занятие «{$this->room->name}»{$started}. Отчёт и запись — на странице занятия.")
            ->icon('clock')
            ->action('Открыть занятие', route('cabinet.teacher.lesson', ['room' => $this->room->id] + ($this->session ? ['session' => $this->session->id] : [])))
            ->toArray();
    }
}
