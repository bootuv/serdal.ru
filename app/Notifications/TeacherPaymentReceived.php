<?php

namespace App\Notifications;

use App\Models\SubscriptionPayment;
use App\Notifications\Messages\CabinetMessage;

/** Администраторам: учитель оплатил тариф или докупил занятия (дублируется в Telegram — SubscriptionService). */
class TeacherPaymentReceived extends CabinetNotification
{
    public function __construct(
        public SubscriptionPayment $payment,
        public string $what,
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $name = $this->payment->user?->name ?? 'Учитель';

        return CabinetMessage::make('Оплата от учителя')
            ->body($name . ' · ' . $this->what . ' — ' . \App\Support\Money::format($this->payment->amount))
            ->icon('check')
            ->action('Открыть платежи', route('cabinet.admin.payments'))
            ->toArray();
    }
}
