<?php

namespace App\Notifications;

use App\Models\Homework;
use App\Notifications\Messages\CabinetMessage;
use App\Services\TeacherLessonService;

/** Ученику: срок сдачи задания через сутки, а работа не сдана (или на доработке). Команда homework:remind-deadlines. */
class HomeworkDeadlineSoon extends CabinetNotification
{
    public function __construct(
        public Homework $homework,
        public bool $revision = false,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Скоро срок сдачи')
            ->body('«' . $this->homework->title . '» — ' . ($this->revision ? 'сдать исправленную работу' : 'сдать') . ' до ' . TeacherLessonService::when($this->homework->deadline))
            ->icon('clock')
            ->action('Открыть задание', route('cabinet.student.task', $this->homework))
            ->toArray();
    }
}
