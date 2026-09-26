<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Review;
use App\Models\User;

class StudentLeftReview extends CabinetNotification
{
    public function __construct(
        public Review $review,
        public User $student
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Новый отзыв')
            ->body($this->student->name . ' · оценка ' . (int) $this->review->rating . ' из 5' . (filled($this->review->text) ? ' — «' . \Illuminate\Support\Str::limit(trim((string) $this->review->text), 120) . '»' : ''))
            ->icon('star')
            ->action('Открыть отзывы', route('cabinet.teacher.reviews'))
            ->toArray();
    }
}
