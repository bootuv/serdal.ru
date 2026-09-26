<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Room;
use App\Notifications\Traits\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/** Учителю: администратор создал для него занятие (ученикам уходит обычное «Новое занятие» — TeacherAssignedLesson). */
class LessonCreatedByAdmin extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public bool $broadcastSound = true;

    /** @param  string  $details  «с Алиной Смирновой · первое — чт, 3 октября в 17:00» */
    public function __construct(public Room $room, public string $details = '')
    {
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
        $details = $this->details !== '' ? ': ' . $this->details : '';

        return CabinetMessage::make('Новое занятие')
            ->body("Администратор создал для вас занятие «{$this->room->name}»{$details}.")
            ->icon('calendar')
            ->action('Открыть', Route::has('cabinet.teacher.lesson')
                        ? route('cabinet.teacher.lesson', ['room' => $this->room->id])
                        : url('/tutor/rooms/' . $this->room->id . '/edit'))
            ->toArray();
    }
}
