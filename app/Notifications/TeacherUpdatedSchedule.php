<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\User;

class TeacherUpdatedSchedule extends CabinetNotification
{
    // Важное уведомление: звук в кабинете
    public bool $broadcastSound = true;

    /**
     * @param  string|null  $title  заголовок («Занятие отменено», «Занятие перенесено»); по умолчанию — «Расписание изменилось»
     * @param  string|null  $body  текст: что и когда изменилось, причина
     * @param  string|null  $roomName  название занятия (строкой: занятие могло уйти в архив)
     * @param  int|null  $roomId  занятие для ссылки; без него — общее расписание
     */
    public function __construct(
        public User $teacher,
        public ?string $title = null,
        public ?string $body = null,
        public ?string $roomName = null,
        public ?int $roomId = null,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $default = $this->teacher->name . ': изменилось расписание ' . ($this->roomName ? 'занятия «' . $this->roomName . '»' : 'занятий');

        return CabinetMessage::make($this->title ?? 'Расписание изменилось')
            ->body($this->body ?? $default)
            ->icon('calendar')
            ->action($this->roomId ? 'Открыть занятие' : 'Расписание', $this->roomId ? route('cabinet.student.lesson', $this->roomId) : route('cabinet.student.schedule'))
            ->toArray();
    }
}
