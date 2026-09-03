<?php

namespace App\Notifications;

use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class SubscriptionRefunded extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public string $tariffName,
        public int $amount,
        public int $processingDays,
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

        return FilamentNotification::make()
            ->title('Возврат оформлен')
            ->body("Возврат {$amountText} ₽ за тариф «{$this->tariffName}» оформлен. Средства вернутся на карту, с которой была оплата, в течение {$this->processingDays} рабочих дней.")
            ->icon('heroicon-o-arrow-uturn-left')
            ->iconColor('info')
            ->actions([
                \Filament\Notifications\Actions\Action::make('history')
                    ->label('История платежей')
                    ->button()
                    ->url(route('filament.app.pages.subscription')),
            ])
            ->getDatabaseMessage();
    }
}
