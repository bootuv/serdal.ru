<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Notifications\Traits\BroadcastsNotification;
use App\Support\HumanDate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** Администратор назначил учителю тариф (окно «Назначить тариф» в карточке учителя). */
class SubscriptionAssigned extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public string $tariffName,
        public ?Carbon $endsAt,
        public bool $complimentary = false,
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
        $body = 'Тариф «' . $this->tariffName . '» ' . ($this->endsAt ? 'действует до ' . HumanDate::date($this->endsAt) : 'действует бессрочно')
            . ($this->complimentary ? ', оплачивать его не нужно.' : '.');

        return CabinetMessage::make('Вам назначен тариф «' . $this->tariffName . '»')
            ->body($body)
            ->icon('wallet')
            ->action('Мой тариф', route('cabinet.teacher.subscription'))
            ->toArray();
    }
}
