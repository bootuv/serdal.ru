<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;

class SessionDeletionApproved extends CabinetNotification
{
    public function __construct(
        public string $roomName,
        public string $startedAt
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Запрос на удаление одобрен')
            ->body("Занятие «{$this->roomName}» ({$this->startedAt}) удалено по вашему запросу — оно не учитывается в истории, статистике и лимите тарифа")
            ->icon('check')
            ->toArray();
    }
}
