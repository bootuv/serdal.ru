<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class PaymentReminder extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public ?User $teacher,
        public int $count = 1
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
        $teacherName = $this->teacher?->name ?? 'преподавателя';

        return FilamentNotification::make()
            ->title('Напоминание об оплате')
            ->body("У вас есть неоплаченные занятия у {$teacherName}. Пожалуйста, не забудьте про оплату.")
            ->icon('heroicon-o-banknotes')
            ->iconColor('warning')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Подробнее')
                    ->button()
                    ->url(route('filament.student.pages.payment-debts'))
            ])
            ->getDatabaseMessage();
    }
}
