<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class TeacherRemoved extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public User $teacher,
        public bool $canLeaveReview = false
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
        $notification = CabinetMessage::make('Прощание с учителем')
            ->icon('users');

        if ($this->canLeaveReview) {
            $notification
                ->body("Учитель {$this->teacher->name} убрал вас из своего списка учеников. Пожалуйста, оставьте отзыв.")
                ->action('Оставить отзыв', route('cabinet.student.home'));
        } else {
            $notification->body("Учитель {$this->teacher->name} убрал вас из своего списка учеников.");
        }

        return $notification->toArray();
    }
}
