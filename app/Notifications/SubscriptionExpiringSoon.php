<?php

namespace App\Notifications;

use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
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

        return FilamentNotification::make()
            ->title('Подписка скоро закончится')
            ->body("Тариф «{$this->tariffName}» закончится {$daysText}. Продлите подписку, чтобы не потерять доступ к возможностям тарифа.")
            ->icon('heroicon-o-clock')
            ->iconColor('warning')
            ->actions([
                \Filament\Notifications\Actions\Action::make('renew')
                    ->label('Продлить')
                    ->button()
                    ->url(route('filament.app.pages.subscription')),
            ])
            ->getDatabaseMessage();
    }
}
