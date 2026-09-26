<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Notifications\Traits\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SubscriptionPaid extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public string $tariffName,
        public int $amount,
        public ?Carbon $endsAt,
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
        $amountText = number_format($this->amount, 0, ',', ' ');
        $untilText = $this->endsAt ? ' Подписка действует до ' . $this->endsAt->format('d.m.Y') . '.' : '';

        return CabinetMessage::make('Подписка оплачена')
            ->body("Оплата {$amountText} ₽ за тариф «{$this->tariffName}» прошла успешно.{$untilText}")
            ->icon('check')
            ->action('Моя подписка', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
