<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Support\HumanDate;
use Illuminate\Support\Carbon;

/** Администратор назначил учителю тариф (окно «Назначить тариф» в карточке учителя). */
class SubscriptionAssigned extends CabinetNotification
{
    protected bool $mail = true;

    public function __construct(
        public string $tariffName,
        public ?Carbon $endsAt,
        public bool $complimentary = false,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $body = 'Тариф «' . $this->tariffName . '» ' . ($this->endsAt ? 'действует до ' . HumanDate::date($this->endsAt) : 'действует бессрочно')
            . ($this->complimentary ? ', оплачивать его не нужно.' : '.');

        return CabinetMessage::make('Вам назначен тариф «' . $this->tariffName . '»')
            ->body($body)
            ->icon('wallet')
            ->action('Тариф и платежи', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
