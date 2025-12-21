<?php

namespace App\Notifications\Traits;

use Illuminate\Notifications\Messages\BroadcastMessage;

trait BroadcastsNotification
{
    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
