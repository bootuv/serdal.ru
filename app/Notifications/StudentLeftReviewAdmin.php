<?php

namespace App\Notifications;

use App\Models\Review;
use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class StudentLeftReviewAdmin extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public Review $review,
        public User $student,
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
        return FilamentNotification::make()
            ->title('Новый отзыв')
            ->body("Ученик {$this->student->name} оставил отзыв учителю {$this->teacher->name}")
            ->icon('heroicon-o-star')
            ->iconColor('warning')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Открыть')
                    ->button()
                    ->url(route('filament.admin.resources.reviews.index'))
            ])
            ->getDatabaseMessage();
    }
}
