<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use Illuminate\Support\Carbon;

class SubscriptionPaid extends CabinetNotification
{
    protected bool $mail = true;

    public function __construct(
        public string $tariffName,
        public int $amount,
        public ?Carbon $endsAt,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Тариф оплачен')
            ->body('Оплата ' . \App\Support\Money::format($this->amount) . ' за тариф «' . $this->tariffName . '» прошла' . ($this->endsAt ? '. Тариф действует до ' . \App\Support\HumanDate::date($this->endsAt) : ''))
            ->icon('check')
            ->action('Тариф и платежи', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
