<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Services\SubscriptionService;

class ReferralBonusCredited extends CabinetNotification
{
    public function __construct(
        public int $lessons,
        public int $balance,
        public string $reason,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $lessonsText = $this->lessons . ' ' . SubscriptionService::lessonsWord($this->lessons);
        $balanceText = $this->balance . ' ' . SubscriptionService::lessonsWord($this->balance);

        return CabinetMessage::make("Вам начислено +{$lessonsText}")
            ->body("{$this->reason} Дополнительных занятий на балансе: {$balanceText}. Они не сгорают и расходуются после лимита тарифа.")
            ->icon('wallet')
            ->action('Пригласить коллег', route('cabinet.teacher.referrals'))
            ->toArray();
    }
}
