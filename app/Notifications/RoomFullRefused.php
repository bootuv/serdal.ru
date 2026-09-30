<?php

namespace App\Notifications;

use App\Models\Room;
use App\Notifications\Messages\CabinetMessage;

/**
 * Учителю: человек не смог войти в класс — все места заняты (лимит участников тарифа или общей настройки).
 * Отправляет RoomCapacityService::refused — не чаще раза в 10 минут на человека.
 */
class RoomFullRefused extends CabinetNotification
{
    // Срочно: человек ждёт у входа во время занятия
    public bool $broadcastSound = true;

    public function __construct(
        public Room $room,
        public string $name,
        public ?int $max = null,
        public ?string $tariffName = null,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $limit = match (true) {
            $this->max && $this->tariffName => ' На тарифе «' . $this->tariffName . '» в занятии до ' . plural_ru($this->max, 'участника', 'участников', 'участников') . ', считая вас.',
            (bool) $this->max => ' В занятии до ' . plural_ru($this->max, 'участника', 'участников', 'участников') . ', считая вас.',
            default => '',
        };

        $message = CabinetMessage::make('В классе нет свободных мест')
            ->body($this->name . ' пытается войти на занятие «' . $this->room->name . '», но все места заняты.' . $limit)
            ->icon('users');

        return ($this->tariffName
            ? $message->action('Тариф и платежи', route('cabinet.teacher.subscription'))
            : $message->action('Открыть занятие', route('cabinet.teacher.lesson', $this->room))
        )->toArray();
    }
}
