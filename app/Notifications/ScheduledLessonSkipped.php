<?php

namespace App\Notifications;

use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

/**
 * Занятие по расписанию не запустилось из-за ограничений подписки
 * (истёк срок или исчерпан лимит занятий).
 */
class ScheduledLessonSkipped extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public string $roomName,
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
        return FilamentNotification::make()
            ->title('Занятие по расписанию не запущено')
            ->body("Комната «{$this->roomName}»: {$this->reason}")
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor('danger')
            ->actions([
                \Filament\Notifications\Actions\Action::make('buy')
                    ->label('Докупить занятия')
                    ->button()
                    ->url(route('filament.app.pages.subscription', ['buy' => 1])),
                \Filament\Notifications\Actions\Action::make('tariffs')
                    ->label('Тарифы')
                    ->url(route('filament.app.pages.subscription')),
            ])
            ->getDatabaseMessage();
    }
}
