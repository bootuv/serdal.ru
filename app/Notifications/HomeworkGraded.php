<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Homework;

class HomeworkGraded extends CabinetNotification
{
    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(
        public Homework $homework,
        public int|float $grade
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Работа проверена')
            ->body('«' . $this->homework->title . '» — оценка ' . $this->homework->formatGrade((int) round($this->grade)))
            ->icon('tasks')
            ->action('Открыть задание', route('cabinet.student.task', $this->homework))
            ->toArray();
    }
}
