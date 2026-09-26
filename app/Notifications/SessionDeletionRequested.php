<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\MeetingSession;
use App\Models\User;

class SessionDeletionRequested extends CabinetNotification
{
    public function __construct(
        public MeetingSession $session,
        public User $teacher
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $roomName = $this->session->room?->name ?? 'Занятие';
        $teacherName = $this->teacher->name ?? 'Учитель';

        return CabinetMessage::make('Запрос на удаление занятия')
            ->body("Учитель {$teacherName} просит удалить проведённое занятие «{$roomName}»")
            ->action('Просмотреть', route('cabinet.admin.session', ['session' => $this->session->id]))
            ->toArray();
    }
}
