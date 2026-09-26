<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class NewSupportMessage extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(
        public SupportMessage $message
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
        $senderName = $this->message->user->name;
        $chatId = $this->message->support_chat_id;

        // Determine the correct URL based on user role
        $role = $notifiable->role;
        $url = null;

        try {
            if ($role === User::ROLE_ADMIN) {
                $url = route('cabinet.admin.support', ['chat' => $chatId]);
            } elseif (in_array($role, [User::ROLE_TUTOR, User::ROLE_STUDENT])) {
                $url = \App\Services\MessengerService::url($notifiable, support: true);
            }
        } catch (\Exception $e) {
            $url = null;
        }

        $notification = CabinetMessage::make("Новое сообщение от поддержки")
            ->body("{$senderName}: " . \Illuminate\Support\Str::limit($this->message->content, 50))
            ->icon('help');

        if ($url) {
            $notification->action('Открыть чат', $url);
        }

        return $notification->toArray();
    }
}
