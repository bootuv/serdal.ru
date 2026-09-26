<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;

class SubscriptionExpired extends CabinetNotification
{
    protected bool $mail = true;

    public function __construct(
        public string $tariffName,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Тариф закончился')
            ->body('Срок тарифа «' . $this->tariffName . '» истёк — новые занятия начать нельзя. Продлите тариф или выберите другой')
            ->icon('bell')
            ->action('Выбрать тариф', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
