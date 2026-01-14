<?php

namespace App\Notifications;

use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class SessionDeletionApproved extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public string $roomName,
        public string $startedAt
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
            ->title('Сессия удалена')
            ->body("Ваш запрос на удаление сессии \"{$this->roomName}\" от {$this->startedAt} одобрен")
            ->icon('heroicon-o-check-circle')
            ->iconColor('success')
            ->success()
            ->getDatabaseMessage();
    }
}
