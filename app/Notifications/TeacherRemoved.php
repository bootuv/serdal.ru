<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class TeacherRemoved extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public User $teacher,
        public bool $canLeaveReview = false
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
        $notification = FilamentNotification::make()
            ->title('Прощание с учителем')
            ->icon('heroicon-o-user-minus')
            ->iconColor('warning');

        if ($this->canLeaveReview) {
            $notification
                ->body("Учитель {$this->teacher->name} убрал вас из своего списка учеников. Пожалуйста, оставьте отзыв.")
                ->actions([
                    \Filament\Notifications\Actions\Action::make('review')
                        ->label('Оставить отзыв')
                        ->button()
                        ->url(route('filament.student.pages.dashboard'))
                ]);
        } else {
            $notification->body("Учитель {$this->teacher->name} убрал вас из своего списка учеников.");
        }

        return $notification->getDatabaseMessage();
    }
}
