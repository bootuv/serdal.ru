<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Review;
use App\Models\User;

class TeacherReportedReview extends CabinetNotification
{
    public function __construct(
        public Review $review,
        public User $teacher
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $studentName = $this->review->user?->name ?? 'Ученик';
        $body = "{$this->teacher->name} · отзыв ученика {$studentName}";

        // Причина и пояснение из окна жалобы (старый кабинет жалуется без причины)
        if ($reason = $this->review->report_reason_label) {
            $body .= ". Причина: {$reason}";
        }
        if (filled($this->review->report_note)) {
            $body .= ' — «' . \Illuminate\Support\Str::limit((string) $this->review->report_note, 300) . '»';
        }

        return CabinetMessage::make('Жалоба на отзыв')
            ->body($body)
            ->icon('star')
            ->action('Открыть', route('cabinet.admin.reviews'))
            ->toArray();
    }
}
