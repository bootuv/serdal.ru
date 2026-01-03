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
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $data = $this->toDatabase($notifiable);

        $title = $data['title'] ?? 'Serdal';
        $body = $data['body'] ?? '';

        // Try to get URL from actions, or from getWebPushUrl method
        $url = null;
        if (isset($data['actions'][0]['url'])) {
            $url = $data['actions'][0]['url'];
        } elseif (method_exists($this, 'getWebPushUrl')) {
            $url = $this->getWebPushUrl($notifiable);
        }

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
