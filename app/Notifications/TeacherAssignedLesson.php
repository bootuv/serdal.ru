<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Room;
use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TeacherAssignedLesson extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(
        public Room $room,
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
        return CabinetMessage::make('Новое занятие')
            ->body("Учитель {$this->teacher->name} назначил вам занятие \"{$this->room->name}\"")
            ->icon('calendar')
            ->action('Открыть', route('cabinet.student.schedule'))
            ->toArray();
    }

}
