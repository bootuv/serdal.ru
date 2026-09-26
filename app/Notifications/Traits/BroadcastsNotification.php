<?php

namespace App\Notifications\Traits;

use Illuminate\Notifications\Messages\BroadcastMessage;
use NotificationChannels\WebPush\WebPushMessage;

trait BroadcastsNotification
{
    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = $this->toDatabase($notifiable);

        // Звук в кабинете играет только для уведомлений с этим флагом
        // (см. resources/views/partials/notification-sound.blade.php).
        // Включается свойством `public bool $broadcastSound = true;` в классе уведомления.
        $data['sound'] = $this->broadcastSound ?? false;

        return new BroadcastMessage($data);
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $data = $this->toDatabase($notifiable);

        $title = $data['title'] ?? 'Serdal';
        $body = $data['body'] ?? '';

        $url = \App\Notifications\Messages\CabinetMessage::urlOf($data)
            ?? (method_exists($this, 'getWebPushUrl') ? $this->getWebPushUrl($notifiable) : null);

        $message = (new WebPushMessage)
            ->title($title)
            ->body($body)
            ->icon('/images/logo-icon.png');

        if ($url) {
            $message->data(['url' => $url]);
        }

        return $message;
    }
}
