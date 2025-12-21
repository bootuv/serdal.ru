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
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Новый отзыв')
            ->body("Ученик {$this->student->name} оставил отзыв учителю {$this->teacher->name}")
            ->icon('heroicon-o-star')
            ->iconColor('warning')
            ->getDatabaseMessage();
    }
}
