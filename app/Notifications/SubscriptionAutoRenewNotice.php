<?php

namespace App\Notifications;

use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Предупреждение о предстоящем автосписании (требование ЮKassa —
 * информировать пользователя перед списанием).
 */
class SubscriptionAutoRenewNotice extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public string $tariffName,
        public int $amount,
        public Carbon $chargeDate,
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
            ->title('Автопродление подписки')
            ->body("{$this->chargeDate->format('d.m.Y')} тариф «{$this->tariffName}» продлится автоматически — с вашей карты спишется {$amountText} ₽. Отключить автопродление можно на странице «Подписка».")
            ->icon('heroicon-o-arrow-path')
            ->iconColor('info')
            ->actions([
                \Filament\Notifications\Actions\Action::make('manage')
                    ->label('Управлять подпиской')
                    ->button()
                    ->url(route('filament.app.pages.subscription')),
            ])
            ->getDatabaseMessage();
    }
}
