<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;

class SubscriptionRefunded extends CabinetNotification
{
    protected bool $mail = true;

    public function __construct(
        public string $title, // «Тариф «Базовый»» или «Дополнительные занятия (4 занятия)»
        public int $amount,
        public int $processingDays,
        public ?\Illuminate\Support\Carbon $newEndsAt = null,
        public bool $subscriptionEnded = false,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $note = match (true) {
            $this->subscriptionEnded => ' Тариф больше не действует.',
            $this->newEndsAt !== null => ' Тариф теперь действует до ' . \App\Support\HumanDate::date($this->newEndsAt) . '.',
            default => '',
        };

        return CabinetMessage::make('Возврат оформлен')
            ->body('Возврат ' . \App\Support\Money::format($this->amount) . ' — ' . $this->title . '. Деньги вернутся тем же способом, которым вы платили, в течение ' . plural_ru($this->processingDays, 'рабочего дня', 'рабочих дней', 'рабочих дней') . '.' . $note)
            ->icon('undo')
            ->action('Тариф и платежи', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
