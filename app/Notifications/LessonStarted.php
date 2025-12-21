<?php

namespace App\Notifications;

use App\Models\Room;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class LessonStarted extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public Room $room
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Занятие началось')
            ->body("Занятие \"{$this->room->name}\" началось")
            ->icon('heroicon-o-play-circle')
            ->iconColor('success')
            ->getDatabaseMessage();
    }
}
