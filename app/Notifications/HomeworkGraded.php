<?php

namespace App\Notifications;

use App\Models\Homework;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Notification;

class HomeworkGraded extends Notification implements ShouldBroadcastNow
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public Homework $homework,
        public int|float $grade
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
            ->title('Работа оценена')
            ->body('Ваша работа "' . $this->homework->title . '" получила оценку: ' . $this->grade)
            ->icon('heroicon-o-academic-cap')
            ->iconColor('success')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Посмотреть')
                    ->button()
                    ->url(route('filament.student.resources.homework.view', $this->homework))
            ])
            ->getDatabaseMessage();
    }
}
