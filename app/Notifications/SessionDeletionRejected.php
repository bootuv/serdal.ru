<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\MeetingSession;
use App\Notifications\Traits\BroadcastsNotification;
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

        return CabinetMessage::make('Запрос отклонён')
            ->body("Ваш запрос на удаление занятия \"{$roomName}\" был отклонён" . ($reply !== '' ? ". Ответ администратора: {$reply}" : ''))
            ->action('Просмотреть', "/tutor/meeting-sessions/{$this->session->id}")
            ->toArray();
    }
}
