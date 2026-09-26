<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\User;

class TeacherCompletedOnboarding extends CabinetNotification
{
    public function __construct(
        public User $teacher
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Учитель прошёл первые шаги')
            ->body("{$this->teacher->name}: профиль, цены и тариф готовы")
            ->icon('check')
            ->action('Открыть карточку', route('cabinet.admin.user', ['user' => $this->teacher->id]))
            ->toArray();
    }
}
