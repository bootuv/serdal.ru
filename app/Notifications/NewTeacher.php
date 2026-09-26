<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\User;

class NewTeacher extends CabinetNotification
{
    public function __construct(
        public User $teacher
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Новый учитель')
            ->body($this->teacher->name . ' — теперь ваш учитель. Занятия, задания и материалы появятся в кабинете')
            ->icon('users')
            ->action('Открыть кабинет', route('cabinet.student.home'))
            ->toArray();
    }
}
