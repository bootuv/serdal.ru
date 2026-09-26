<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\User;

class PaymentReminder extends CabinetNotification
{
    protected bool $mail = true;

    public function __construct(
        public ?User $teacher,
        public int $count = 1
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $teacherName = $this->teacher?->name ?? 'учителя';

        return CabinetMessage::make('Напоминание об оплате')
            ->body('Есть неоплаченные занятия у ' . $teacherName . ($this->count > 1 ? ' — ' . plural_ru($this->count, 'занятие', 'занятия', 'занятий') : '') . '. Оплатите их, чтобы занятия этого учителя оставались открыты')
            ->icon('wallet')
            ->action('Открыть оплату', route('cabinet.student.payments'))
            ->toArray();
    }
}
