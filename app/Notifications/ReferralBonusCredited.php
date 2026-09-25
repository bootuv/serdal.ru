<?php

namespace App\Notifications;

use App\Notifications\Traits\BroadcastsNotification;
use App\Services\SubscriptionService;
use Filament\Notifications\Notification as FilamentNotification;
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

        return FilamentNotification::make()
            ->title("Вам начислено +{$lessonsText}")
            ->body("{$this->reason} Дополнительных занятий на балансе: {$balanceText}. Они не сгорают и расходуются после лимита тарифа.")
            ->icon('heroicon-o-gift')
            ->iconColor('success')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Пригласить ещё')
                    ->button()
                    ->url(route('filament.app.pages.referrals')),
            ])
            ->getDatabaseMessage();
    }
}
