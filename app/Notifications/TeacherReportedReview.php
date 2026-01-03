<?php

namespace App\Notifications;

use App\Models\Review;
use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class TeacherReportedReview extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public Review $review,
        public User $teacher
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
        $studentName = $this->review->user?->name ?? 'Ученик';

        return FilamentNotification::make()
            ->title('Жалоба на отзыв')
            ->body("Учитель {$this->teacher->name} пожаловался на отзыв ученика {$studentName}")
            ->icon('heroicon-o-flag')
            ->iconColor('danger')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Открыть')
                    ->button()
                    ->url(route('filament.admin.resources.reviews.index'))
            ])
            ->getDatabaseMessage();
    }
}
