<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\PaymentClaim;
use Illuminate\Support\Facades\Route;

/** Ученику: учитель подтвердил или отклонил сообщение об оплате. */
class PaymentClaimDecided extends CabinetNotification
{
    public bool $broadcastSound = true;

    public function __construct(public PaymentClaim $claim)
    {
    }

    public function toDatabase(object $notifiable): array
    {
        $claim = $this->claim;
        $teacher = $claim->teacher?->name ?? 'Учитель';
        $confirmed = $claim->status === PaymentClaim::STATUS_CONFIRMED;

        $body = $confirmed
            ? $teacher . ': оплата' . ($claim->amount ? ' ' . \App\Support\Money::format($claim->amount) : '') . ' подтверждена.'
            : $teacher . ': оплата не подтверждена'
                . ($claim->reject_reason ? ' — «' . \Illuminate\Support\Str::limit($claim->reject_reason, 200) . '»' : '')
                . '. Напишите учителю или сообщите об оплате ещё раз.';

        return CabinetMessage::make($confirmed ? 'Оплата подтверждена' : 'Оплата не подтверждена')
            ->body($body)
            ->icon('wallet')
            ->action('Открыть оплату', route('cabinet.student.payments'))
            ->toArray();
    }
}
