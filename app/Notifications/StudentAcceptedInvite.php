<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\User;

class StudentAcceptedInvite extends CabinetNotification
{
    public function __construct(
        public User $student
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Новый ученик')
            ->body($this->student->name . ' теперь в вашем списке учеников')
            ->icon('users')
            ->action('Открыть ученика', \App\Services\TeacherStudentsService::studentUrl($this->student))
            ->toArray();
    }
}
