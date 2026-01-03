<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class TeacherCompletedOnboarding extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
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
        return FilamentNotification::make()
            ->title('Онбординг пройден')
            ->body("Учитель {$this->teacher->name} прошёл онбординг")
            ->icon('heroicon-o-check-badge')
            ->iconColor('success')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Открыть')
                    ->button()
                    ->url(route('filament.admin.resources.users.index', ['tableFilters[role][value]' => 'tutor']))
            ])
            ->getDatabaseMessage();
    }
}
