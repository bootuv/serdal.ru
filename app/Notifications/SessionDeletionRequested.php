<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\MeetingSession;
use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
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
        $roomName = $this->session->room?->name ?? 'Занятие';
        $teacherName = $this->teacher->name ?? 'Учитель';

        return CabinetMessage::make('Запрос на удаление занятия')
            ->body("Учитель {$teacherName} просит удалить проведённое занятие «{$roomName}»")
            ->action('Просмотреть', route('cabinet.admin.session', ['session' => $this->session->id]))
            ->toArray();
    }
}
