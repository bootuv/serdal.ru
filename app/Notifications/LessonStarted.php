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
        $channels = ['database', 'broadcast'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = \NotificationChannels\WebPush\WebPushChannel::class;
        }

        return $channels;
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

    /**
     * Get URL for push notification click action.
     */
    public function getWebPushUrl(object $notifiable): string
    {
        // Determine the correct URL based on user role
        if ($notifiable->role === 'tutor' || $notifiable->role === 'admin') {
            return \App\Filament\App\Resources\RoomResource::getUrl('view', ['record' => $this->room->id]);
        } elseif ($notifiable->role === 'student') {
            return \App\Filament\Student\Resources\RoomResource::getUrl('view', ['record' => $this->room->id]);
        }

        return '/';
    }
}
