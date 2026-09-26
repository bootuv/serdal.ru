<?php

namespace App\Notifications;

use App\Models\Room;
use App\Notifications\Messages\CabinetMessage;
use App\Support\HumanDate;
use Illuminate\Support\Carbon;

/**
 * Напоминание учителю и ученикам занятия: скоро начало по расписанию (команда lessons:remind, за 15 минут).
 * Учителю — «Начать занятие» (страница занятия), ученику — страница занятия, где появится «Войти в класс».
 */
class LessonStartingSoon extends CabinetNotification
{
    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(
        public Room $room,
        public Carbon $start,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $teacher = $notifiable->id === $this->room->user_id;
        $minutes = max(1, (int) ceil(now()->diffInSeconds($this->start, false) / 60));

        return CabinetMessage::make('Скоро занятие')
            ->body('«' . $this->room->name . '» ' . HumanDate::at($this->start) . ' — через ' . plural_ru($minutes, 'минуту', 'минуты', 'минут'))
            ->icon('clock')
            ->action($teacher ? 'Начать занятие' : 'Открыть занятие', $this->getWebPushUrl($notifiable))
            ->toArray();
    }

    public function getWebPushUrl(object $notifiable): string
    {
        return $notifiable->id === $this->room->user_id
            ? route('cabinet.teacher.lesson', $this->room)
            : route('cabinet.student.lesson', $this->room);
    }
}
