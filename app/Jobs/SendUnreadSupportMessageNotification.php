<?php

namespace App\Jobs;

use App\Models\SupportMessage;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendUnreadSupportMessageNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public SupportMessage $message,
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
            $senderName = $this->message->user->name;
            $chatId = $this->message->support_chat_id;

            // Determine the correct URL based on user role
            $role = $this->recipient->role;
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
                // If route doesn't exist, just skip the URL
                $url = null;
            }

            $notification = Notification::make()
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

            $notification
                ->sendToDatabase($this->recipient)
                ->broadcast($this->recipient);
        }
    }
}
