<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;

/** Учителю: администратор удалил проведённое занятие без запроса учителя. */
class SessionDeletedByAdmin extends CabinetNotification
{
    /** @param  string  $when  «чт, 12 сентября в 17:00» */
    public function __construct(public string $roomName, public string $when)
    {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Занятие удалено')
            ->body("Администратор удалил проведённое занятие «{$this->roomName}» ({$this->when}). Оно больше не учитывается в истории, статистике и лимите тарифа.")
            ->icon('calendar')
            ->toArray();
    }
}
