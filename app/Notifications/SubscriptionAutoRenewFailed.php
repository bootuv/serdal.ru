<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;

class SubscriptionAutoRenewFailed extends CabinetNotification
{
    protected bool $mail = true;

    public function __construct(
        public string $tariffName,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Не получилось продлить тариф')
            ->body('Автоматическое списание за тариф «' . $this->tariffName . '» не прошло — возможно, на карте не хватает денег или она заблокирована. Оплатите тариф вручную, чтобы занятия не остановились')
            ->icon('bell')
            ->action('Оплатить тариф', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
