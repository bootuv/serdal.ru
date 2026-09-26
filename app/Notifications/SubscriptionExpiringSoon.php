<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use Illuminate\Support\Carbon;

class SubscriptionExpiringSoon extends CabinetNotification
{
    protected bool $mail = true;

    public function __construct(
        public string $tariffName,
        public Carbon $endsAt,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $days = max(0, (int) ceil(now()->diffInHours($this->endsAt, false) / 24));
        $when = $days <= 0 ? 'сегодня' : 'через ' . plural_ru($days, 'день', 'дня', 'дней') . ', ' . \App\Support\HumanDate::date($this->endsAt);

        return CabinetMessage::make('Тариф скоро закончится')
            ->body('Тариф «' . $this->tariffName . '» закончится ' . $when . '. Продлите его, чтобы занятия не остановились')
            ->icon('clock')
            ->action('Продлить тариф', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
