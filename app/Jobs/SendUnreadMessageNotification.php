<?php

namespace App\Jobs;

use App\Models\Message;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendUnreadMessageNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Message $message,
        public $recipient
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Проверяем актуальное состояние сообщения из базы
        if ($this->message->fresh()->read_at === null) {
            $roomName = $this->message->room->name;
            $senderName = $this->message->user->name;
            $roomId = $this->message->room_id;

            // Determine the correct URL based on user role
            $role = $this->recipient->role;
            $url = null;

            try {
                if (in_array($role, [\App\Models\User::ROLE_TUTOR, \App\Models\User::ROLE_MENTOR])) {
                    $url = route('filament.app.pages.messenger', ['room' => $roomId]);
                } elseif ($role === \App\Models\User::ROLE_STUDENT) {
                    $url = route('filament.student.pages.messenger', ['room' => $roomId]);
                }
            } catch (\Exception $e) {
                // If route doesn't exist, just skip the URL
                $url = null;
            }

            $notification = Notification::make()
                ->title("Новое сообщение в \"{$roomName}\"")
                ->body("{$senderName}: " . \Illuminate\Support\Str::limit($this->message->content, 50))
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->iconColor('info');

            if ($url) {
                $notification->actions([
                    Action::make('view')
                        ->label('Открыть чат')
                        ->button()
                        ->url($url),
                ]);
            }

            $notification
                ->sendToDatabase($this->recipient)
                ->broadcast($this->recipient);
        }
    }
}
