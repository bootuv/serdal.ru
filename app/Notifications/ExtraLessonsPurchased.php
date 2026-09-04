<?php

namespace App\Notifications;

use App\Notifications\Traits\BroadcastsNotification;
use App\Services\SubscriptionService;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class ExtraLessonsPurchased extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public int $quantity,
        public int $amount,
        public int $balance,
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
        $quantityText = $this->quantity . ' ' . SubscriptionService::lessonsWord($this->quantity);
        $balanceText = $this->balance . ' ' . SubscriptionService::lessonsWord($this->balance);

        return FilamentNotification::make()
            ->title('Дополнительные занятия зачислены')
            ->body("Оплата {$amountText} ₽ прошла успешно, зачислено {$quantityText}. Докупленных занятий на балансе: {$balanceText}. Они не сгорают и расходуются после лимита тарифа.")
            ->icon('heroicon-o-check-circle')
            ->iconColor('success')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Моя подписка')
                    ->button()
                    ->url(route('filament.app.pages.subscription')),
            ])
            ->getDatabaseMessage();
    }
}
