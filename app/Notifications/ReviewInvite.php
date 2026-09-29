<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Messages\CabinetMessage;

/**
 * Приглашение ученику оставить отзыв об учителе: учитель попросил сам (byTeacher)
 * или прошло три занятия (ReviewPromptService::inviteAfterLessons). Ссылка сразу открывает окно отзыва.
 */
class ReviewInvite extends CabinetNotification
{
    public function __construct(
        public User $teacher,
        public bool $byTeacher = false,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return CabinetMessage::make($this->byTeacher ? 'Учитель просит отзыв' : 'Как вам занятия?')
            ->body($this->byTeacher
                ? $this->teacher->name . ' просит оставить отзыв о занятиях. Его увидят ученики, которые выбирают учителя.'
                : 'Оцените занятия с учителем: ' . $this->teacher->name . '. Отзыв поможет другим ученикам выбрать учителя.')
            ->icon('star')
            ->action('Оставить отзыв', route('cabinet.student.home', ['review' => $this->teacher->id]))
            ->toArray();
    }
}
