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
        public MeetingSession $session,
        public ?string $reply = null
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
        $roomName = $this->session->room?->name ?? 'Занятие';
        $reply = trim((string) $this->reply);

        return FilamentNotification::make()
            ->title('Запрос отклонён')
            ->body("Ваш запрос на удаление занятия \"{$roomName}\" был отклонён" . ($reply !== '' ? ". Ответ администратора: {$reply}" : ''))
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
