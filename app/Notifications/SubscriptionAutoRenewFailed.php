<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Notifications\Traits\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class SubscriptionAutoRenewFailed extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public string $tariffName,
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
        return CabinetMessage::make('Не удалось продлить подписку')
            ->body("Автосписание за тариф «{$this->tariffName}» не прошло — возможно, на карте недостаточно средств или она заблокирована. Продлите подписку вручную, чтобы не потерять доступ.")
            ->icon('bell')
            ->action('Оплатить вручную', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
