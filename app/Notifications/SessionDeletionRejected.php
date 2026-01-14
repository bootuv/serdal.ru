<?php

namespace App\Notifications;

use App\Models\MeetingSession;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class SessionDeletionRejected extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public MeetingSession $session
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

        return FilamentNotification::make()
            ->title('Запрос отклонён')
            ->body("Ваш запрос на удаление сессии \"{$roomName}\" был отклонён")
            ->danger()
            ->actions([
                Action::make('view')
                    ->label('Просмотреть')
                    ->url("/tutor/meeting-sessions/{$this->session->id}")
                    ->button()
                    ->color('danger')
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
