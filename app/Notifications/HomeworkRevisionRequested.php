<?php

namespace App\Notifications;

use App\Models\Homework;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class HomeworkRevisionRequested extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public Homework $homework,
        public string $feedback
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
            ->title('Требуется доработка')
            ->body('Ваша работа "' . $this->homework->title . '" отправлена на доработку')
            ->icon('heroicon-o-arrow-path')
            ->iconColor('danger')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Посмотреть')
                    ->button()
                    ->url(route('filament.student.resources.homework.view', $this->homework))
            ])
            ->getDatabaseMessage();
    }
}
