<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Notifications\Traits\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SubscriptionExpiringSoon extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public string $tariffName,
        public Carbon $endsAt,
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
        $daysLeft = max(0, (int) now()->diffInDays($this->endsAt, false));
        $daysText = $daysLeft <= 0 ? 'сегодня' : 'через ' . $daysLeft . ' дн. (' . $this->endsAt->format('d.m.Y') . ')';

        return CabinetMessage::make('Подписка скоро закончится')
            ->body("Тариф «{$this->tariffName}» закончится {$daysText}. Продлите подписку, чтобы не потерять доступ к возможностям тарифа.")
            ->icon('clock')
            ->action('Продлить', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
