<?php

namespace App\Notifications;

use App\Models\MeetingSession;
use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class SessionDeletionRequested extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public MeetingSession $session,
        public User $teacher
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
        $roomName = $this->session->room?->name ?? 'Урок';
        $teacherName = $this->teacher->name ?? 'Учитель';

        return FilamentNotification::make()
            ->title('Запрос на удаление сессии')
            ->body("Учитель {$teacherName} запросил удаление сессии \"{$roomName}\"")
            ->warning()
            ->actions([
                Action::make('view')
                    ->label('Просмотреть')
                    ->url("/admin/meeting-sessions/{$this->session->id}")
                    ->button()
                    ->color('warning')
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
