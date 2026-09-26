<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\User;

class HomeworkSubmitted extends CabinetNotification
{
    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(
        public Homework $homework,
        public User $student,
        public ?HomeworkSubmission $submission = null
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Новая работа')
            ->body($this->student->name . ' · «' . $this->homework->title . '»')
            ->icon('tasks')
            ->action('Проверить', $this->url())
            ->toArray();
    }

    /** Сразу на проверку работы в новом кабинете; без работы — экран задания. */
    private function url(): string
    {
        $submission = $this->submission
            ?? $this->homework->submissions()->where('student_id', $this->student->id)->first();

        return $submission !== null
            ? route('cabinet.teacher.review', $submission)
            : route('cabinet.teacher.task', $this->homework);
    }
}
