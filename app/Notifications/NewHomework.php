<?php

namespace App\Notifications;

use App\Models\Homework;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Notification;

class NewHomework extends Notification implements ShouldBroadcastNow
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public Homework $homework
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
            ->title('Новое задание')
            ->body('Вам назначено: ' . $this->homework->title)
            ->icon($this->homework->type_icon)
            ->iconColor($this->homework->type_color)
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Открыть')
                    ->button()
                    ->url(route('filament.student.resources.homework.view', $this->homework))
            ])
            ->getDatabaseMessage();
    }
}
