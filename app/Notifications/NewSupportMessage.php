<?php

namespace App\Notifications;

use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class NewSupportMessage extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

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
                $url = route('filament.admin.pages.admin-messenger', ['chat' => $chatId]);
            } elseif (in_array($role, [User::ROLE_TUTOR, User::ROLE_MENTOR])) {
                $url = route('filament.app.pages.messenger', ['support' => '1']);
            } elseif ($role === User::ROLE_STUDENT) {
                $url = route('filament.student.pages.messenger', ['support' => '1']);
            }
        } catch (\Exception $e) {
            $url = null;
        }

        $notification = FilamentNotification::make()
            ->title("Новое сообщение от поддержки")
            ->body("{$senderName}: " . \Illuminate\Support\Str::limit($this->message->content, 50))
            ->icon('heroicon-o-lifebuoy')
            ->iconColor('warning');

        if ($url) {
            $notification->actions([
                Action::make('view')
                    ->label('Открыть чат')
                    ->button()
                    ->url($url),
            ]);
        }

        return $notification->getDatabaseMessage();
    }
}
