<?php

namespace App\Notifications;

use App\Models\Room;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class TeacherAssignedLesson extends Notification implements ShouldBroadcastNow
{
    public function __construct(
        public Room $room,
        public User $teacher
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Новое занятие')
            ->body("Учитель {$this->teacher->name} назначил вам занятие \"{$this->room->name}\"")
            ->icon('heroicon-o-calendar')
            ->iconColor('info')
            ->getDatabaseMessage();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
