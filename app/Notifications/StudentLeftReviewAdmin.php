<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Review;
use App\Models\User;

class StudentLeftReviewAdmin extends CabinetNotification
{
    public function __construct(
        public Review $review,
        public User $student,
        public User $teacher
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Новый отзыв')
            ->body("{$this->student->name} → {$this->teacher->name}")
            ->icon('star')
            ->action('Открыть', route('cabinet.admin.reviews', ['tab' => 'all']))
            ->toArray();
    }
}
