<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Review;
use App\Notifications\Traits\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/** Учителю: администратор рассмотрел его жалобу на отзыв — отзыв скрыт или остаётся. Приходит и письмом. */
class ReviewReportDecided extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public const HIDDEN = 'hidden';

    public const KEPT = 'kept';

    public function __construct(public Review $review, public string $decision)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast', 'mail'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = \NotificationChannels\WebPush\WebPushChannel::class;
        }

        return $channels;
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
        return Route::has('cabinet.teacher.reviews') ? route('cabinet.teacher.reviews') : url('/tutor/reviews');
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make($this->title())
            ->body($this->body())
            ->icon('star')
            ->action('Открыть отзывы', $this->url())
            ->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title() . ' — ' . \App\Support\Seo::SITE_NAME)
            ->greeting('Здравствуйте!')
            ->line($this->body())
            ->action('Открыть отзывы', $this->url());
    }
}
