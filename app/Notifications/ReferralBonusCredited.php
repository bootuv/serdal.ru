<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Notifications\Traits\BroadcastsNotification;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class ReferralBonusCredited extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public int $lessons,
        public int $balance,
        public string $reason,
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = \NotificationChannels\WebPush\WebPushChannel::class;
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        $lessonsText = $this->lessons . ' ' . SubscriptionService::lessonsWord($this->lessons);
        $balanceText = $this->balance . ' ' . SubscriptionService::lessonsWord($this->balance);

        return CabinetMessage::make("Вам начислено +{$lessonsText}")
            ->body("{$this->reason} Дополнительных занятий на балансе: {$balanceText}. Они не сгорают и расходуются после лимита тарифа.")
            ->icon('wallet')
            ->action('Пригласить ещё', route('cabinet.teacher.referrals'))
            ->toArray();
    }
}
