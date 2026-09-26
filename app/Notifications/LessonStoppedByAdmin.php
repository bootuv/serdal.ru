<?php

namespace App\Notifications;

use App\Models\MeetingSession;
use App\Models\Room;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/** Учителю: администратор завершил его идущее занятие (админка, «Завершить занятие»). */
class LessonStoppedByAdmin extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public bool $broadcastSound = true;

    public function __construct(public Room $room, public ?MeetingSession $session = null)
    {
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
        $started = $this->session?->started_at ? ', начатое в ' . $this->session->started_at->format('H:i') : '';

        return FilamentNotification::make()
            ->title('Занятие завершено администратором')
            ->body("Администратор завершил занятие «{$this->room->name}»{$started}. Отчёт и запись — на странице занятия.")
            ->icon('heroicon-o-clock')
            ->iconColor('gray')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Открыть')
                    ->button()
                    ->url(Route::has('cabinet.teacher.lesson')
                        ? route('cabinet.teacher.lesson', ['room' => $this->room->id] + ($this->session ? ['session' => $this->session->id] : []))
                        : url('/tutor/rooms/' . $this->room->id . '/edit')),
            ])
            ->getDatabaseMessage();
    }
}
