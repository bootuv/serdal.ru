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
        public ?\Illuminate\Support\Carbon $newEndsAt = null,
        public bool $subscriptionEnded = false,
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

        $subscriptionNote = '';
        if ($this->subscriptionEnded) {
            $subscriptionNote = ' Действие подписки завершено.';
        } elseif ($this->newEndsAt) {
            $subscriptionNote = ' Срок подписки скорректирован — тариф действует до ' . $this->newEndsAt->format('d.m.Y') . '.';
        }

        return FilamentNotification::make()
            ->title('Возврат оформлен')
            ->body("Возврат {$amountText} ₽ за тариф «{$this->tariffName}» оформлен. Средства вернутся на карту, с которой была оплата, в течение {$this->processingDays} рабочих дней.{$subscriptionNote}")
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
