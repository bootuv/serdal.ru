<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Review;

/** Учителю: администратор рассмотрел его жалобу на отзыв — отзыв скрыт или остаётся. Приходит и письмом. */
class ReviewReportDecided extends CabinetNotification
{
    protected bool $mail = true;

    public const HIDDEN = 'hidden';

    public const KEPT = 'kept';

    public function __construct(public Review $review, public string $decision)
    {
    }

    private function title(): string
    {
        return $this->decision === self::HIDDEN ? 'Отзыв скрыт' : 'Жалоба на отзыв рассмотрена';
    }

    private function body(): string
    {
        $student = $this->review->user?->name ?? 'ученика';

        return $this->decision === self::HIDDEN
            ? "Мы проверили вашу жалобу на отзыв ученика {$student} и скрыли его. Отзыв больше не виден на вашей странице и не влияет на оценку."
            : "Мы проверили вашу жалобу на отзыв ученика {$student}: нарушений не нашли, отзыв остаётся на вашей странице.";
    }

    private function url(): string
    {
        return route('cabinet.teacher.reviews');
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make($this->title())
            ->body($this->body())
            ->icon('star')
            ->action('Открыть отзывы', $this->url())
            ->toArray();
    }
}
