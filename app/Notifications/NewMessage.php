<?php

namespace App\Notifications;

use App\Models\Message;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class NewMessage extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public Message $message
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        $roomName = $this->message->room->name;
        $senderName = $this->message->user->name;
        $roomId = $this->message->room_id;

        // Determine the correct URL based on user role
        $user = $notifiable;
        if ($user->hasRole('tutor')) {
            $url = \App\Filament\App\Pages\Messenger::getUrl(['room' => $roomId]);
        } elseif ($user->hasRole('student')) {
            $url = \App\Filament\Student\Pages\Messenger::getUrl(['room' => $roomId]);
        } else {
            $url = null;
        }

        $notification = FilamentNotification::make()
            ->title("Новое сообщение в \"{$roomName}\"")
            ->body("{$senderName}: " . \Illuminate\Support\Str::limit($this->message->content, 50))
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->iconColor('info');

        if ($url) {
            $notification->actions([
                Action::make('view')
                    ->label('Открыть чат')
                    ->url($url)
                    ->button()
                    ->color('primary')
                    ->markAsRead(),
            ]);
        }

        return $notification->getDatabaseMessage();
    }
}
