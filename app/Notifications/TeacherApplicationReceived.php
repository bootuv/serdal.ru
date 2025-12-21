<?php

namespace App\Notifications;

use App\Models\TeacherApplication;
use App\Notifications\Traits\BroadcastsNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class TeacherApplicationReceived extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public function __construct(
        public TeacherApplication $application
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        $name = trim("{$this->application->last_name} {$this->application->first_name}");

        return FilamentNotification::make()
            ->title('Новая заявка учителя')
            ->body("Получена заявка на регистрацию от {$name}")
            ->icon('heroicon-o-document-text')
            ->iconColor('info')
            ->getDatabaseMessage();
    }
}
