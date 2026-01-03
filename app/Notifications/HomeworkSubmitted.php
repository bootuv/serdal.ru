<?php

namespace App\Notifications;

use App\Models\Homework;
use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class HomeworkSubmitted extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public Homework $homework,
        public User $student
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
            ->title('Новая работа')
            ->body($this->student->name . ' сдал(а) работу: ' . $this->homework->title)
            ->icon('heroicon-o-clipboard-document-check')
            ->iconColor('warning')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Проверить')
                    ->button()
                    ->url(route('filament.app.resources.homework.view', $this->homework))
            ])
            ->getDatabaseMessage();
    }
}
