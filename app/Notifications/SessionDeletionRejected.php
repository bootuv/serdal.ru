<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\MeetingSession;

class SessionDeletionRejected extends CabinetNotification
{
    public function __construct(
        public MeetingSession $session,
        public ?string $reply = null
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $roomName = $this->session->room?->name ?? 'Занятие';
        $reply = trim((string) $this->reply);

        return CabinetMessage::make('Запрос на удаление отклонён')
            ->body("Занятие «{$roomName}» остаётся в истории" . ($reply !== '' ? ". Ответ администратора: «{$reply}»" : ''))
            ->action('Открыть занятие', $this->session->room_id ? route('cabinet.teacher.lesson', ['room' => $this->session->room_id, 'session' => $this->session->id]) : route('cabinet.teacher.schedule'))
            ->toArray();
    }
}
