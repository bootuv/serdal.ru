<?php

namespace App\Notifications;

use App\Models\TeacherApplication;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class TeacherApplicationReceived extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public TeacherApplication $application
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
        $name = trim("{$this->application->last_name} {$this->application->first_name}");

        return FilamentNotification::make()
            ->title('Новая заявка учителя')
            ->body("Получена заявка на регистрацию от {$name}")
            ->icon('heroicon-o-document-text')
            ->iconColor('info')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Открыть')
                    ->button()
                    ->url(route('filament.admin.resources.teacher-applications.index'))
            ])
            ->getDatabaseMessage();
    }
}
