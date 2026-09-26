<?php

namespace App\Notifications;

use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

/** Учителю: администратор удалил проведённое занятие без запроса учителя. */
class SessionDeletedByAdmin extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    /** @param  string  $when  «чт, 12 сентября в 17:00» */
    public function __construct(public string $roomName, public string $when)
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
        return FilamentNotification::make()
            ->title('Занятие удалено')
            ->body("Администратор удалил проведённое занятие «{$this->roomName}» ({$this->when}). Оно больше не учитывается в истории, статистике и лимите тарифа.")
            ->icon('heroicon-o-calendar')
            ->iconColor('gray')
            ->getDatabaseMessage();
    }
}
