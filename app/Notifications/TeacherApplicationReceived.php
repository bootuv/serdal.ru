<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\TeacherApplication;

class TeacherApplicationReceived extends CabinetNotification
{
    public function __construct(
        public TeacherApplication $application
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $name = trim("{$this->application->last_name} {$this->application->first_name}");

        return CabinetMessage::make('Новая заявка учителя')
            ->body("{$name} хочет преподавать на Serdal")
            ->icon('tasks')
            ->action('Открыть заявки', route('cabinet.admin.applications'))
            ->toArray();
    }
}
