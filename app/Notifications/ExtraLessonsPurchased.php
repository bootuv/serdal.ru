<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Services\SubscriptionService;

class ExtraLessonsPurchased extends CabinetNotification
{
    protected bool $mail = true;

    public function __construct(
        public int $quantity,
        public int $amount,
        public int $balance,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Занятия зачислены')
            ->body('Оплата ' . \App\Support\Money::format($this->amount) . ' прошла: +' . plural_ru($this->quantity, 'занятие', 'занятия', 'занятий') . '. Докупленных на балансе — ' . $this->balance . ', они не сгорают и расходуются после лимита тарифа')
            ->icon('check')
            ->action('Тариф и платежи', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
