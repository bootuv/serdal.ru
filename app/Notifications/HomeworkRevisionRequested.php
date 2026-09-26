<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\Homework;

class HomeworkRevisionRequested extends CabinetNotification
{
    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(
        public Homework $homework,
        public string $feedback
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make('Работу нужно доработать')
            ->body('«' . $this->homework->title . '» вернули на доработку — посмотрите комментарий учителя и сдайте ещё раз')
            ->icon('repeat')
            ->action('Открыть задание', route('cabinet.student.task', $this->homework))
            ->toArray();
    }
}
