<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Review;
use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class StudentLeftReview extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public Review $review,
        public User $student
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
        return CabinetMessage::make('Новый отзыв')
            ->body("Ученик {$this->student->name} оставил вам отзыв")
            ->icon('star')
            ->action('Открыть', route('cabinet.teacher.reviews'))
            ->toArray();
    }
}
