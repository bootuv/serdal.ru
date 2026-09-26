<?php

namespace App\Notifications;

use App\Models\Review;
use App\Notifications\Messages\CabinetMessage;
use Illuminate\Support\Str;

/** Администраторам: учитель оставил или изменил отзыв о платформе — проверить и опубликовать. */
class PlatformReviewSubmitted extends CabinetNotification
{
    public function __construct(
        public Review $review,
        public bool $isNew = true,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $author = $this->review->user?->name ?? 'Учитель';

        return CabinetMessage::make($this->isNew ? 'Отзыв о платформе' : 'Отзыв о платформе изменён')
            ->body($author . ' · оценка ' . (int) $this->review->rating . ' из 5 — «' . Str::limit((string) $this->review->text, 120) . '»')
            ->icon('star')
            ->action('Проверить отзыв', route('cabinet.admin.reviews', ['tab' => 'platform']))
            ->toArray();
    }
}
