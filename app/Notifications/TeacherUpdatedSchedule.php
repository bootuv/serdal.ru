<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class TeacherUpdatedSchedule extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    /**
     * @param  string|null  $title  заголовок («Занятие отменено», «Занятие перенесено»); по умолчанию — «Расписание обновлено»
     * @param  string|null  $body  текст: что и когда изменилось, причина
     */
    public function __construct(
        public User $teacher,
        public ?string $title = null,
        public ?string $body = null,
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
            ->title($this->title ?? 'Расписание обновлено')
            ->body($this->body ?? "Учитель {$this->teacher->name} обновил ваше расписание занятий")
            ->icon('heroicon-o-calendar-days')
            ->iconColor('info')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Расписание')
                    ->button()
                    ->url(\Illuminate\Support\Facades\Route::has('cabinet.student.schedule')
                        ? route('cabinet.student.schedule')
                        : route('filament.student.pages.schedule-calendar'))
            ])
            ->getDatabaseMessage();
    }
}
