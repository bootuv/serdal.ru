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
        $body = "Учитель {$this->teacher->name} пожаловался на отзыв ученика {$studentName}";

        // Причина и пояснение из окна жалобы (старый кабинет жалуется без причины)
        if ($reason = $this->review->report_reason_label) {
            $body .= ". Причина: {$reason}";
        }
        if (filled($this->review->report_note)) {
            $body .= ' — «' . \Illuminate\Support\Str::limit((string) $this->review->report_note, 300) . '»';
        }

        return FilamentNotification::make()
            ->title('Жалоба на отзыв')
            ->body($body)
            ->icon('heroicon-o-flag')
            ->iconColor('danger')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Открыть')
                    ->button()
                    ->url(route('cabinet.admin.reviews'))
            ])
            ->getDatabaseMessage();
    }
}
