<?php

namespace App\Notifications;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class HomeworkSubmitted extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(
        public Homework $homework,
        public User $student,
        public ?HomeworkSubmission $submission = null
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
            ->title('Новая работа')
            ->body($this->student->name . ' сдал(а) работу: ' . $this->homework->title)
            ->icon('heroicon-o-clipboard-document-check')
            ->iconColor('warning')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Проверить')
                    ->button()
                    ->url($this->url())
            ])
            ->getDatabaseMessage();
    }

    /** Сразу на проверку работы в новом кабинете; без работы — экран задания. */
    private function url(): string
    {
        $submission = $this->submission
            ?? $this->homework->submissions()->where('student_id', $this->student->id)->first();

        return match (true) {
            $submission !== null && \Illuminate\Support\Facades\Route::has('cabinet.teacher.review') => route('cabinet.teacher.review', $submission),
            \Illuminate\Support\Facades\Route::has('cabinet.teacher.task') => route('cabinet.teacher.task', $this->homework),
            default => route('filament.app.resources.homework.view', $this->homework),
        };
    }
}
