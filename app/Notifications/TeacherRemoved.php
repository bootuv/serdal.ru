<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\User;

class TeacherRemoved extends CabinetNotification
{
    public function __construct(
        public User $teacher,
        public bool $canLeaveReview = false
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $notification = CabinetMessage::make('Занятия с учителем завершены')
            ->icon('users')
            ->body($this->teacher->name . ' больше не ведёт с вами занятия' . ($this->canLeaveReview ? '. Оставьте отзыв — он поможет другим ученикам выбрать учителя' : ''));

        if ($this->canLeaveReview) {
            $notification->action('Оставить отзыв', route('cabinet.student.home'));
        }

        return $notification->toArray();
    }
}
