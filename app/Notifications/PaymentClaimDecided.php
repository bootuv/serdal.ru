<?php

namespace App\Notifications;

use App\Notifications\Messages\CabinetMessage;
use App\Models\PaymentClaim;
use App\Notifications\Traits\BroadcastsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/** Ученику: учитель подтвердил или отклонил сообщение об оплате. */
class PaymentClaimDecided extends Notification implements ShouldBroadcast
{
    use Queueable, BroadcastsNotification;

    public bool $broadcastSound = true;

    public function __construct(public PaymentClaim $claim)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($notifiable->pushSubscriptions()->exists()) {
            $channels[] = \NotificationChannels\WebPush\WebPushChannel::class;
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        $claim = $this->claim;
        $teacher = $claim->teacher?->name ?? 'Учитель';
        $confirmed = $claim->status === PaymentClaim::STATUS_CONFIRMED;

        $body = $confirmed
            ? $teacher . ' подтвердил(а) оплату' . ($claim->amount ? ' ' . \App\Support\Money::format($claim->amount) : '') . '.'
            : $teacher . ' не подтвердил(а) оплату'
                . ($claim->reject_reason ? ': «' . \Illuminate\Support\Str::limit($claim->reject_reason, 200) . '»' : '')
                . '. Напишите учителю или сообщите об оплате ещё раз.';

        return CabinetMessage::make($confirmed ? 'Оплата подтверждена' : 'Оплата не подтверждена')
            ->body($body)
            ->icon('wallet')
            ->action('Подробнее', Route::has('cabinet.student.payments') ? route('cabinet.student.payments') : url('/student/payment-debts'))
            ->toArray();
    }
}
