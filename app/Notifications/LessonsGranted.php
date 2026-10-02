<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;

class LessonsGranted extends CabinetNotification
{
    protected bool $mail = true;

    public function __construct(
        public int $quantity,
        public int $balance,
        public bool $withoutTariff,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Администрация добавила занятия')
            ->body('+' . plural_ru($this->quantity, 'занятие', 'занятия', 'занятий') . ', на балансе — ' . $this->balance . '. '
                . ($this->withoutTariff
                    ? 'Их можно провести и без действующего тарифа, а продлить тариф — когда будет удобно'
                    : 'Они не сгорают и расходуются после лимита тарифа'))
            ->icon('check')
            ->action('Тариф и платежи', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
