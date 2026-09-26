<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Homework;

class NewHomework extends CabinetNotification
{
    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(
        public Homework $homework
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $deadline = $this->homework->deadline;

        return CabinetMessage::make('Новое задание')
            ->body('«' . $this->homework->title . '»' . ($deadline ? ' — сдать до ' . \App\Services\TeacherLessonService::when($deadline) : ''))
            ->icon('tasks')
            ->action('Открыть задание', route('cabinet.student.task', $this->homework))
            ->toArray();
    }
}
