<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\PaymentClaim;
use App\Services\TeacherStudentsService;

/** Учителю: ученик сообщил об оплате занятий (с чеком). Ссылка — карточка ученика, вкладка «Оплата». */
class PaymentClaimSubmitted extends CabinetNotification
{
    // Важное уведомление: играть звук в кабинете при получении
    public bool $broadcastSound = true;

    public function __construct(public PaymentClaim $claim)
    {
    }

    public function toDatabase(object $notifiable): array
    {
        $claim = $this->claim;
        $student = $claim->student;
        $count = $claim->records()->count();

        $body = ($student?->name ?? 'Ученик') . ': оплачено '
            . plural_ru($count, 'занятие', 'занятия', 'занятий')
            . ($claim->amount ? ' · ' . \App\Support\Money::format($claim->amount) : '')
            . '. Проверьте чек и подтвердите оплату.';

        return CabinetMessage::make('Ученик сообщил об оплате')
            ->body($body)
            ->icon('wallet')
            ->action('Проверить оплату', $student ? TeacherStudentsService::studentUrl($student, ['tab' => 'pay']) : route('cabinet.teacher.students'))
            ->toArray();
    }
}
